<?php

namespace App\Support;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Mail\Glockenmeldung;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Wer erfährt wovon.
 *
 * Alle Benachrichtigungen des Systems laufen durch diese Klasse, und zwar
 * absichtlich: der Empfängerkreis ist die Stelle, an der aus einem
 * Kundenbereich ein Datenleck wird. Stünde neben jedem Auslöser sein eigenes
 * "und wen benachrichtigen wir jetzt", müsste jede dieser Stellen einzeln
 * daran denken, dass ein Kundenzugang nur Dinge aus seinem eigenen Projekt
 * erfahren darf — und in der Betreffzeile einer Benachrichtigung steht immer
 * schon der halbe Inhalt.
 *
 * Zwei Richtungen gibt es:
 *
 *   nachInnen()  — an uns. Admins und die Zuständigen des Projekts.
 *   nachAussen() — an den Kunden. Alle Zugänge seines Kunden.
 */
class Benachrichtigung
{
    /**
     * An uns: Admins und alle, die für dieses Ticket zuständig sind.
     *
     * Admins sind immer dabei, weil sie ohnehin alles sehen und im Zweifel
     * die Einzigen sind, die reagieren können — ein Kundenanliegen in einem
     * Projekt ohne zugeordneten Mitarbeiter würde sonst niemanden erreichen.
     */
    public static function nachInnen(Ticket $ticket, Notification $meldung, MailEreignis $ereignis): void
    {
        self::zustellen(self::innenkreis($ticket), $meldung, Herkunft::ticket($ticket), $ticket->customer, $ereignis);
    }

    /**
     * An den Kunden: alle Zugänge, die zu diesem Kunden gehören.
     *
     * Verborgene Projekte sind hier ausgenommen. Das ist der Fall, den man
     * beim Umschalten von kunden_sichtbar leicht übersieht: das Projekt
     * verschwindet zwar aus seiner Liste, eine Benachrichtigung darüber wäre
     * aber trotzdem noch bei ihm gelandet — mit dem Projektnamen im Text.
     */
    public static function nachAussen(Ticket $ticket, Notification $meldung, MailEreignis $ereignis): void
    {
        self::zustellen(self::aussenkreis($ticket), $meldung, Herkunft::ticket($ticket), $ticket->customer, $ereignis);
    }

    /**
     * An die Zuständigen eines Kunden: Admins und die ihm zugeordneten
     * Mitarbeiter.
     *
     * Ohne Bezug auf ein Ticket, weil es Dinge gibt, die keines haben — ein
     * Kunde, der seine Rechnungsanschrift ändert, zum Beispiel. Genau dieser
     * Fall ist der Grund für die Methode: eine stille Änderung an
     * Stammdaten fällt erst auf, wenn die nächste Rechnung zurückkommt.
     */
    public static function anZustaendige(int $customerId, Notification $meldung, MailEreignis $ereignis): void
    {
        self::zustellen(self::zustaendige($customerId), $meldung, Herkunft::kunde($customerId), Customer::find($customerId), $ereignis);
    }

    /**
     * Wer bei uns für diesen Kunden zuständig ist.
     *
     * Öffentlich, weil nicht nur Benachrichtigungen diesen Kreis brauchen:
     * die Unterhaltungen fragen ihn ebenfalls, um zu wissen, wer eine
     * Nachricht des Kunden zu sehen bekommt. Zweimal formuliert wäre er
     * spätestens bei der nächsten Änderung an den Zuordnungen zweierlei.
     *
     * @return Collection<int, User>
     */
    public static function zustaendige(int $customerId): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->where('rolle', '!=', Rolle::Kunde->value)
            ->where(fn (Builder $q) => $q
                ->where('rolle', Rolle::Admin->value)
                ->orWhereHas('customers', fn (Builder $c) => $c->whereKey($customerId))
                ->orWhereHas('projects', fn (Builder $p) => $p->where('customer_id', $customerId)))
            ->get();
    }

    /**
     * Alle nutzbaren Zugänge eines Kunden.
     *
     * Ohne Bezug auf ein Ticket — anders als aussenkreis(), das zusätzlich
     * prüft, ob das betroffene Projekt für den Kunden überhaupt sichtbar ist.
     * Für eine Unterhaltung gibt es diese Einschränkung nicht: sie hängt an
     * keinem Projekt.
     *
     * @return Collection<int, User>
     */
    public static function kundenzugaenge(int $customerId): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->where('panel_zugang', true)
            ->where('rolle', Rolle::Kunde->value)
            ->where('customer_id', $customerId)
            ->get();
    }

    /**
     * An einen fertig ermittelten Kreis.
     *
     * Der Ausnahmefall, und deshalb ausdrücklich benannt: die Unterhaltungen
     * kennen ihren Empfängerkreis selbst — bei einem internen Faden sind es
     * genau die beiden Beteiligten, und keine der Regeln oben trifft darauf
     * zu. Der Weg zur Glocke soll trotzdem derselbe bleiben.
     *
     * @param  Collection<int, User>  $empfaenger
     */
    public static function an(
        Collection $empfaenger,
        Notification $meldung,
        ?string $herkunft = null,
        ?Customer $kunde = null,
        ?MailEreignis $ereignis = null,
    ): void {
        self::zustellen($empfaenger, $meldung, $herkunft, $kunde, $ereignis);
    }

    /**
     * Alles, was zu dieser Sache gemeldet wurde, gilt für diesen Nutzer als
     * gelesen.
     *
     * Wird von den Stellen gerufen, an denen jemand die Sache tatsächlich vor
     * sich hat: ein geöffnetes Ticket, ein geöffneter Verlauf, eine geöffnete
     * Kundenakte. Der Gedanke dahinter ist derselbe wie beim Lesestand der
     * Unterhaltungen — gelesen ist, was jemand gesehen hat, und nicht, was er
     * zusätzlich weggeklickt hat.
     *
     * Die Meldung bleibt in der Glocke stehen; sie zählt nur nicht mehr mit.
     * Das ist Absicht: die Glocke ist auch ein kleines Gedächtnis ("wann kam
     * das noch mal rein"), und eine Liste, die sich beim Lesen selbst löscht,
     * kann das nicht sein.
     *
     * @return int wie viele Meldungen dadurch gelesen wurden
     */
    public static function gesehen(?User $nutzer, string $herkunft): int
    {
        if ($nutzer === null) {
            return 0;
        }

        // Die Abfrage geht über die data-Spalte, die in dieser Anwendung
        // ausdrücklich json ist und nicht text — siehe die Migration der
        // notifications-Tabelle. Auf text kennt Postgres den ->>-Operator
        // nicht, und diese Bedingung liefe in denselben 500er wie damals
        // Filaments Glocke.
        return $nutzer->unreadNotifications()
            ->where('data->herkunft', $herkunft)
            ->update(['read_at' => now()]);
    }

    /**
     * Zustellen — und zwar sofort, nicht über die Warteschlange.
     *
     * Filaments sendToDatabase() ruft notify() auf, und die zugrunde liegende
     * DatabaseNotification ist ein ShouldQueue. Bei QUEUE_CONNECTION=database
     * heißt das: die Benachrichtigung landet in der jobs-Tabelle und wartet
     * dort auf einen Worker. Einen solchen gibt es in diesem Projekt nicht —
     * weder lokal noch im Container —, und deshalb kam vor dieser Zeile
     * schlicht nie etwas an der Glocke an. Aufgefallen ist es erst beim
     * Ausprobieren im Browser; in den Tests läuft die Warteschlange auf
     * "sync" und alles sah richtig aus.
     *
     * notifyNow() umgeht die Warteschlange. Das ist hier auch sachlich
     * richtig: eine Datenbank-Benachrichtigung ist ein einzelnes INSERT.
     * Dafür einen zweiten Dauerprozess zu betreiben, der ausfallen und
     * unbemerkt stehenbleiben kann, wäre mehr Betrieb als Nutzen.
     *
     * @param  Collection<int, User>  $empfaenger
     */
    private static function zustellen(
        Collection $empfaenger,
        Notification $meldung,
        ?string $herkunft = null,
        ?Customer $kunde = null,
        ?MailEreignis $ereignis = null,
    ): void {
        // Die Herkunft wird der fertigen Meldung untergemischt statt über
        // Filament gesetzt: dessen Notification kennt nur ihre eigenen Felder,
        // und getDatabaseMessage() wirft alles andere weg. Beim Zurücklesen
        // stört der zusätzliche Schlüssel nicht — Notification::fromArray()
        // greift sich die Felder, die es kennt, und übergeht den Rest.
        $daten = $meldung->getDatabaseMessage();

        if ($herkunft !== null) {
            $daten['herkunft'] = $herkunft;
        }

        foreach ($empfaenger as $nutzer) {
            $nutzer->notifyNow(new DatabaseNotification($daten));
        }

        self::perMailNachreichen($empfaenger, $daten, $kunde, $ereignis);

        // Filaments DatabaseNotificationsSent wird hier bewusst NICHT
        // ausgelöst. Das Ereignis ist ein ShouldBroadcast und landet damit
        // seinerseits als Job in der Warteschlange — dieselbe Falle noch
        // einmal, nur eine Ebene tiefer, und bei BROADCAST_CONNECTION=log
        // ohne jeden Nutzen. Die Glocke fragt ohnehin jede Minute nach
        // (databaseNotificationsPolling in beiden Panel-Providern); eine
        // offene Oberfläche hat die Meldung also spätestens nach 60 Sekunden.

    }

    /**
     * Dieselbe Meldung noch einmal per Mail — an die, die das eingeschaltet
     * haben.
     *
     * Hier und nirgends sonst, aus demselben Grund, aus dem der ganze
     * Empfängerkreis hier liegt: es gibt genau einen Weg zur Glocke, und
     * damit gibt es auch genau einen zur Mail. Eine zweite Stelle, die
     * Mails verschickt, müsste die Regel „wer darf was erfahren" ein zweites
     * Mal kennen.
     *
     * **Der Versand läuft nach der Antwort** (defer). Ein SMTP-Handshake
     * dauert eine bis zwei Sekunden; synchron hinge die daran, die das
     * Ereignis ausgelöst hat — beim gemeldeten Anliegen also der Kunde, der
     * gerade auf „Absenden" gedrückt hat. Eine Warteschlange bräuchte einen
     * Worker, den es hier nicht gibt (siehe README).
     *
     * Fehler beim Versand werden protokolliert und sonst verschluckt. Das
     * ist Absicht: eine Meldung an der Glocke darf nicht daran scheitern,
     * dass ein Mailserver gerade nicht erreichbar ist. Wer wissen will,
     * warum keine Mail ankam, findet die Zeile im Protokoll.
     *
     * @param  Collection<int, User>  $empfaenger
     * @param  array<string, mixed>  $daten
     */
    private static function perMailNachreichen(
        Collection $empfaenger,
        array $daten,
        ?Customer $kunde = null,
        ?MailEreignis $ereignis = null,
    ): void {
        $ziele = $empfaenger->filter(fn (User $nutzer) => $nutzer->bekommtMailMeldungen($ereignis));

        if ($ziele->isEmpty()) {
            return;
        }

        $titel = (string) ($daten['title'] ?? 'Neue Meldung an Bord');
        $text = $daten['body'] ?? null;

        // Der Knopf aus der Meldung. Er zeigt schon in das richtige Panel —
        // eine Meldung nach außen trägt eine /kunde-Adresse, eine nach innen
        // die interne (siehe urlIntern/urlKunde). Hier ist also nichts zu
        // entscheiden, nur zu übernehmen.
        $url = $daten['actions'][0]['url'] ?? null;

        // Die Farbe der Meldung wandert mit: sie färbt den Streifen über der
        // Mail, so wie sie an der Glocke den Punkt am Rand färbt.
        $farbe = $daten['color'] ?? null;

        // Das Logo hier auflösen und nicht erst im Versand: danach laeuft
        // alles ausserhalb der Anfrage, und eine Datenbankabfrage dort
        // waere eine, die niemand mehr sieht, wenn sie schiefgeht.
        $logo = $kunde?->logoUrl();
        $kundenName = $kunde?->name;

        // Die Zieladressen jetzt auflösen, nicht erst im Versand: danach
        // läuft alles außerhalb der Anfrage. Für einen Kundenzugang ist das
        // die von ihm bestätigte Adresse, intern die Anmeldeadresse
        // (User::mailZieladresse).
        // Neben der Adresse wandert mit, ob dahinter ein Kundenzugang steht.
        // Die Fußzeile der Mail sagt, wo man den Versand abschaltet, und die
        // Antwort ist für beide Seiten eine andere: intern im Maschinenraum,
        // beim Kunden unter "Mein Konto". Vorher stand dort für alle der
        // interne Weg — ein Kunde las also eine Anleitung für eine Seite,
        // die er gar nicht aufrufen kann.
        $adressen = $ziele
            ->map(fn (User $nutzer) => [
                'adresse' => $nutzer->mailZieladresse(),
                'kunde' => $nutzer->istKunde(),
            ])
            ->filter(fn (array $ziel) => filled($ziel['adresse']))
            ->values();

        if ($adressen->isEmpty()) {
            return;
        }

        defer(function () use ($adressen, $titel, $text, $url, $farbe, $logo, $kundenName) {
            foreach ($adressen as $ziel) {
                try {
                    Mail::to($ziel['adresse'])->send(
                        new Glockenmeldung($titel, $text, $url, $farbe, $logo, $kundenName, $ziel['kunde']),
                    );
                } catch (\Throwable $fehler) {
                    Log::warning('Meldung konnte nicht per Mail zugestellt werden.', [
                        'empfaenger' => $ziel['adresse'],
                        'titel' => $titel,
                        'fehler' => $fehler->getMessage(),
                    ]);
                }
            }
        });
    }

    /** @return Collection<int, User> */
    public static function innenkreis(Ticket $ticket): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->where('rolle', '!=', Rolle::Kunde->value)
            ->where(fn (Builder $q) => $q
                ->where('rolle', Rolle::Admin->value)
                // Nur wenn überhaupt jemand zuständig ist: ohne die Prüfung
                // würde daraus "id is null" und die Bedingung liefe leer —
                // harmlos, aber irreführend beim Lesen.
                ->when(
                    $ticket->assigned_to !== null,
                    fn (Builder $z) => $z->orWhere('id', $ticket->assigned_to),
                )
                ->orWhereHas('projects', fn (Builder $p) => $p->whereKey($ticket->project_id))
                ->orWhereHas('customers', fn (Builder $c) => $c->whereKey($ticket->customer_id)))
            ->get();
    }

    /** @return Collection<int, User> */
    public static function aussenkreis(Ticket $ticket): Collection
    {
        if (! $ticket->project?->kunden_sichtbar) {
            return collect();
        }

        return self::kundenzugaenge($ticket->customer_id);
    }

    /**
     * Der Knopf unter einer Benachrichtigung, der zum Ticket führt.
     *
     * Ohne ihn ist eine Benachrichtigung eine Mitteilung, mit ihm ein
     * Arbeitsschritt: man liest "Fehler gemeldet" und ist einen Klick später
     * dort, wo man etwas tun kann. markAsRead(), weil eine gelesene Meldung,
     * die man wegklicken muss, nach dem dritten Mal ignoriert wird.
     */
    public static function knopf(string $beschriftung, string $url): Action
    {
        return Action::make('oeffnen')
            ->label($beschriftung)
            ->url($url)
            ->markAsRead();
    }

    /**
     * Die Adresse eines Tickets im internen Panel.
     *
     * Das Panel wird ausdrücklich angegeben. Ohne den Parameter nähme
     * getUrl() das gerade aktive — und aktiv ist beim Auslösen einer
     * Benachrichtigung immer das Panel dessen, der die Änderung gemacht hat.
     * Meldet also ein Kunde etwas, entstünde für uns ein Link nach /kunde.
     */
    public static function urlIntern(Ticket $ticket): string
    {
        return TicketResource::getUrl('view', ['record' => $ticket], panel: 'admin');
    }

    /** Die Adresse desselben Tickets im Kundenbereich. */
    public static function urlKunde(Ticket $ticket): string
    {
        return AnliegenResource::getUrl('view', ['record' => $ticket], panel: 'kunde');
    }
}
