<?php

namespace App\Support;

use App\Enums\Erinnerung;
use App\Enums\MailEreignis;
use App\Filament\Kunde\Pages\Uebersicht;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Treffen\TreffenResource;
use App\Models\Treffen;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Wer zu einem Treffen gehört — und wer davon erfährt.
 *
 * Die eine Stelle, durch die das Einladen läuft. Sie steht hier und nicht im
 * Formular, weil es später mehr als einen Weg zu einem Treffen geben wird
 * (ein wiederkehrender Termin, ein Vorschlag aus einer Nachricht), und jeder
 * davon müsste sonst wissen, wen er zu benachrichtigen hat.
 */
class Messe
{
    /**
     * Setzt die Crew eines Treffens und meldet sich bei den Neuen.
     *
     * Nur bei den Neuen: wer schon dabei war, hat seine Einladung. Ein
     * Termin, an dem man dreimal die Beschreibung nachbessert, schickte
     * sonst dreimal dieselbe Meldung — und nach der zweiten liest sie
     * niemand mehr.
     *
     * @param  array<int, int|string>  $userIds
     */
    public static function crewSetzen(Treffen $treffen, array $userIds): void
    {
        $vorher = $treffen->crew()->pluck('users.id');

        $treffen->crew()->sync($userIds);

        $neue = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $vorher->contains($id));

        if ($neue->isEmpty()) {
            return;
        }

        $empfaenger = User::query()->whereKey($neue)->get();

        // Wer sich selbst einträgt, braucht darüber keine Meldung — er hat
        // das Formular gerade ausgefüllt.
        $empfaenger = $empfaenger->reject(fn (User $nutzer) => $nutzer->is(auth()->user()));

        if ($empfaenger->isEmpty()) {
            return;
        }

        self::anCrew($treffen, $empfaenger, 'Du bist dabei');
    }

    /**
     * Meldet allen aus der Crew etwas zu diesem Treffen.
     *
     * @param  Collection<int, User>|null  $nur  sonst die ganze Crew
     */
    public static function anCrew(
        Treffen $treffen,
        ?Collection $nur = null,
        string $anlass = 'Termin geändert',
        string $farbe = 'info',
    ): void {
        $empfaenger = $nur ?? self::crewOderErsteller($treffen);

        if ($empfaenger->isEmpty()) {
            return;
        }

        $treffen->loadMissing('customer');

        Benachrichtigung::an(
            $empfaenger,
            Notification::make()
                ->title($anlass.': '.$treffen->titel)
                ->body(self::wann($treffen).' · '.($treffen->customer?->name ?? 'nur wir'))
                ->icon('heroicon-o-video-camera')
                ->color($farbe)
                ->actions([
                    Benachrichtigung::knopf('Ansehen', self::urlIntern($treffen)),
                ]),
            Herkunft::treffen($treffen),
            $treffen->customer,
            // Ein eigenes Thema und nicht MailEreignis::Treffen: das geht
            // nach außen. Wer hier Mail bekommt, hat "Meine Treffen"
            // angehakt — und kann genau das einzeln wieder abschalten,
            // ohne alles andere mit abzubestellen.
            MailEreignis::TreffenCrew,
        );
    }

    /** Der Termin in einem Satz, wie er in jeder Meldung steht. */
    public static function wann(Treffen $treffen): string
    {
        return $treffen->beginnt_am->translatedFormat('l, j. F Y \u\m H:i \U\h\r');
    }

    /**
     * Wohin der Knopf einer Meldung führt.
     *
     * Es gibt keine eigene Seite je Treffen — ein Termin ist eine Zeile,
     * keine Akte. Bei einem Kundentermin ist die Akte der richtige Ort, bei
     * einer Team-Besprechung gibt es keine: dann führt er auf die Messe.
     */
    public static function urlIntern(Treffen $treffen): string
    {
        if ($treffen->istIntern()) {
            return TreffenResource::getUrl('index', panel: 'admin');
        }

        return CustomerResource::getUrl(
            'view',
            ['record' => $treffen->customer_id],
            panel: 'admin',
        );
    }

    /**
     * Wer von uns Bescheid bekommt — und wenn niemand eingetragen ist, die
     * Person, die den Termin angesetzt hat.
     *
     * Der Rückfall ist der Normalfall bei internen Terminen: wer sich selbst
     * eine Wochenplanung in den Kalender legt, hakt sich in der Crew nicht
     * unbedingt an. Ohne diese Zeile ginge die Erinnerung an einen leeren
     * Kreis — der Termin, an den am ehesten erinnert werden muss, wäre
     * genau der, an den niemand erinnert wird.
     *
     * @return Collection<int, User>
     */
    public static function crewOderErsteller(Treffen $treffen): Collection
    {
        $crew = $treffen->crew()->where('aktiv', true)->get();

        if ($crew->isNotEmpty()) {
            return $crew;
        }

        $ersteller = $treffen->erstellerIn;

        return $ersteller?->aktiv ? collect([$ersteller]) : collect();
    }

    /**
     * An den Kunden: eine Einladung, eine Änderung, eine Erinnerung.
     *
     * Liegt hier und nicht mehr im Observer, seit es zwei Auslöser gibt. Der
     * Observer meldet, was jemand am Termin getan hat; der Planer meldet,
     * dass er näher rückt. Beides ist aus Sicht des Kunden dieselbe Karte —
     * und derselbe Empfängerkreis, den es nur einmal geben darf.
     *
     * Ohne Kunden tut die Methode nichts: ein interner Termin hat keine
     * Gegenseite.
     */
    public static function anKunden(Treffen $treffen, string $anlass, string $farbe = 'info'): void
    {
        $treffen->loadMissing('customer');

        if ($treffen->customer === null || ! $treffen->kunden_sichtbar) {
            return;
        }

        $schiff = (string) config('kontakt.schiff');

        Benachrichtigung::an(
            Benachrichtigung::kundenzugaenge($treffen->customer_id),
            Notification::make()
                ->title($anlass.': '.$treffen->titel)
                ->body(self::wann($treffen).' an Bord der '.$schiff)
                ->icon('heroicon-o-video-camera')
                ->color($farbe)
                ->actions([
                    Benachrichtigung::knopf('Ansehen', Uebersicht::getUrl(panel: 'kunde')),
                ]),
            Herkunft::treffen($treffen),
            $treffen->customer,
            MailEreignis::Treffen,
        );
    }

    /**
     * Die Erinnerung einer Stufe — an beide Seiten.
     *
     * Erst an uns, dann an den Kunden. Die Reihenfolge ist nicht gleichgültig:
     * scheitert etwas dazwischen, ist die interne Meldung die, die man lieber
     * hat — der Kunde hat den Termin ohnehin in seinem Kalender stehen, wir
     * sind die, die ihn halten müssen.
     *
     * Was die Stufe nach außen und nach innen unterscheidet, ist allein das
     * Wort davor: "Morgen: Abnahme" liest sich in beide Richtungen gleich.
     */
    public static function erinnern(Treffen $treffen, Erinnerung $stufe): void
    {
        $anlass = $stufe->anlass($treffen);

        self::anCrew($treffen, null, $anlass);
        self::anKunden($treffen, $anlass);
    }

    /**
     * Die Stempel beider Stufen zurücknehmen — nach einer Verschiebung.
     *
     * saveQuietly, weil das Zurücknehmen kein Ereignis ist, über das jemand
     * etwas erfahren müsste: es passiert mitten im updated() des Observers,
     * und ein normales save() liefe von dort aus in denselben Beobachter
     * zurück.
     */
    public static function erinnerungenZuruecksetzen(Treffen $treffen): void
    {
        $treffen->forceFill([
            Erinnerung::Tag->spalte() => null,
            Erinnerung::Stunde->spalte() => null,
        ])->saveQuietly();
    }

    /**
     * Die Treffen, für die diese Stufe jetzt dran ist — und die dabei sofort
     * abgehakt werden.
     *
     * Der Stempel wird gesetzt, BEVOR die Meldung rausgeht, und zwar in
     * einem bedingten UPDATE ("nur wenn noch nicht gestempelt"). Wer die
     * Zeile bekommt, hat sie exklusiv: liefen zwei Planer gleichzeitig oder
     * überschnitten sich zwei Läufe, verschickte sonst jeder von beiden
     * dieselbe Erinnerung. Von den beiden möglichen Fehlern ist das der
     * schlimmere — eine doppelte Meldung sieht nach einem kaputten System
     * aus, eine ausgefallene sieht nach nichts aus.
     *
     * Abgehakt wird auch, was gar nicht verschickt wird (siehe
     * Erinnerung::lohntSich). Sonst prüfte der Planer denselben Termin jede
     * Minute erneut.
     *
     * @return Collection<int, Treffen>
     */
    public static function faellige(Erinnerung $stufe): Collection
    {
        $kandidaten = Treffen::query()
            ->zuErinnern($stufe)
            ->with(['customer', 'crew', 'erstellerIn'])
            ->get();

        return $kandidaten->filter(fn (Treffen $treffen) => Treffen::query()
            ->whereKey($treffen->getKey())
            ->whereNull($stufe->spalte())
            ->update([$stufe->spalte() => now()]) === 1)->values();
    }
}
