<?php

namespace App\Support;

use App\Enums\Rolle;
use App\Mail\Sammelmeldung;
use App\Models\Postausgang;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Der Postausgang wird geleert: eine Mail je Empfänger statt einer je Meldung
 * (NID-35).
 *
 * Vorher ging jede Meldung als eigene Mail hinaus. Stellte ein Kunde in ein
 * paar Minuten fünfzig kleine Anliegen ein, kamen fünfzig Mails — und wer
 * abends zwanzig Tickets auf erledigt setzte, schickte dem Kunden zwanzig.
 * Jetzt sammelt Benachrichtigung::perMailNachreichen() diese Fälle
 * (MailEreignis::gesammelt), und der Planer schickt sie gebündelt:
 *
 *   intern  — täglich um 9 und 17 Uhr, auch am Wochenende.
 *   Kunden  — werktags um 7 Uhr, samstags um 10, sonntags nie. Früh am
 *             Morgen, weil vieles erst abends und nachts erledigt wird; so
 *             steht in der Mail der ganze Vortag samt Nacht.
 *
 * **Beim Leeren wird noch einmal geprüft**, denn zwischen Meldung und Versand
 * kann ein Tag liegen:
 *
 *  - Will der Empfänger das noch? Schalter und Themenwahl können sich
 *    geändert haben.
 *  - Hat er es schon gesehen? Wer das Ticket inzwischen geöffnet hat, dessen
 *    Glockenmeldung ist gelesen (Benachrichtigung::gesehen) — eine Mail dazu
 *    wäre eine über etwas, das er schon weiß. Eine aus der Glocke
 *    *gelöschte* Meldung zählt dagegen nicht als gesehen: gelesen ist, was
 *    jemand gesehen hat, nicht was er weggeklickt hat.
 *  - Darf er es noch erfahren? Ein Kunde nur, solange das Projekt für ihn
 *    freigegeben ist. Das ist dieselbe Regel wie in
 *    Benachrichtigung::aussenkreis(), nur ein zweites Mal angewandt, weil
 *    ein inzwischen verborgenes Projekt sonst mit Namen in seiner Mail
 *    stünde.
 *  - Ist es intern noch offen, und ist es noch seins? Erledigtes fällt
 *    heraus, und ein Mitarbeiter bekommt nichts, was ein anderer hat.
 *
 * Zur selben Sache bleibt nur die letzte Meldung desselben Themas stehen:
 * drei Antworten unter einem Ticket sind eine Zeile, kein Absatz.
 */
class Sammelmail
{
    /**
     * Leert den Postausgang einer Seite.
     *
     * @param  bool  $kunden  true: die Kundenzugänge, false: alle internen
     * @return int an wie viele eine Mail ging
     */
    public static function leeren(bool $kunden): int
    {
        $nutzerIds = Postausgang::query()
            ->whereHas('user', fn (Builder $q) => $kunden
                ? $q->where('rolle', Rolle::Kunde->value)
                : $q->where('rolle', '!=', Rolle::Kunde->value))
            ->distinct()
            ->pluck('user_id');

        $versandt = 0;

        foreach ($nutzerIds as $id) {
            $nutzer = User::find($id);

            // Nur bis zur letzten jetzt gelesenen Zeile löschen: was während
            // des Versands hinzukommt, wartet auf die nächste Runde.
            $posten = Postausgang::query()->where('user_id', $id)->orderBy('id')->get();
            $bis = $posten->max('id');

            $offen = $nutzer === null ? collect() : self::auslesen($nutzer, $posten);

            if ($offen->isNotEmpty() && filled($nutzer->mailZieladresse())) {
                try {
                    Mail::to($nutzer->mailZieladresse())->send(new Sammelmeldung(
                        $offen->map(fn (Postausgang $p) => [
                            'titel' => $p->titel,
                            'text' => $p->text,
                            'url' => $p->url,
                            'farbe' => $p->farbe,
                            'kunde' => $p->customer?->name,
                        ])->values()->all(),
                        $nutzer->istKunde(),
                    ));

                    $versandt++;
                } catch (\Throwable $fehler) {
                    // Wie beim Einzelversand: protokolliert und sonst
                    // verschluckt. Die Glocke hat ohnehin alles.
                    Log::warning('Sammelmail konnte nicht zugestellt werden.', [
                        'empfaenger' => $nutzer->mailZieladresse(),
                        'meldungen' => $offen->count(),
                        'fehler' => $fehler->getMessage(),
                    ]);
                }
            }

            Postausgang::query()->where('user_id', $id)->where('id', '<=', $bis)->delete();
        }

        return $versandt;
    }

    /**
     * Was von den gesammelten Meldungen tatsächlich in die Mail gehört.
     *
     * @param  Collection<int, Postausgang>  $posten
     * @return Collection<int, Postausgang>
     */
    private static function auslesen(User $nutzer, Collection $posten): Collection
    {
        $gelesen = $nutzer->notifications()
            ->whereIn('id', $posten->pluck('notification_id')->filter()->all())
            ->whereNotNull('read_at')
            ->pluck('id')
            ->all();

        return $posten
            ->filter(fn (Postausgang $p) => $nutzer->bekommtMailMeldungen($p->ereignis))
            ->reject(fn (Postausgang $p) => in_array($p->notification_id, $gelesen, true))
            ->filter(fn (Postausgang $p) => self::nochErlaubt($nutzer, $p))
            // Zur selben Sache und zum selben Thema gewinnt die letzte
            // Meldung. Ohne Herkunft steht jede für sich.
            ->keyBy(fn (Postausgang $p) => $p->herkunft !== null
                ? $p->ereignis->value.'|'.$p->herkunft
                : 'einzeln|'.$p->getKey())
            ->sortBy('id')
            ->values();
    }

    /**
     * Gibt es die Sache noch, und geht sie den Empfänger noch etwas an?
     *
     * Nur Tickets werden nachgeprüft. Sie sind das Einzige, was zwischen
     * Meldung und Versand verschwinden, verborgen oder erledigt werden kann;
     * ein gelöschtes Ticket hinterließe sonst einen Knopf ins Leere.
     *
     * Intern ist die Mail eine Liste dessen, was noch offen ist, und kein
     * Protokoll: was bis zum Versand abgeschlossen wurde, fällt heraus. Ein
     * Mitarbeiter bekommt außerdem nichts, was inzwischen ein anderer hat —
     * Kevin steht in Projekten, in denen die meisten Tickets bei Nils liegen,
     * und eine Mail über die Arbeit eines anderen ist keine Hilfe, sondern
     * Lärm. Admins sehen weiter alles, weil sie im Zweifel die Einzigen sind,
     * die reagieren können (Benachrichtigung::nachInnen). An der Glocke
     * ändert das nichts.
     */
    private static function nochErlaubt(User $nutzer, Postausgang $posten): bool
    {
        if (! str_starts_with((string) $posten->herkunft, 'ticket:')) {
            return true;
        }

        $ticket = Ticket::with(['project', 'status'])->find((int) substr($posten->herkunft, strlen('ticket:')));

        if ($ticket === null) {
            return false;
        }

        if ($nutzer->istKunde()) {
            return $ticket->customer_id === $nutzer->customer_id
                && (bool) $ticket->project?->istFuerKundenFreigegeben();
        }

        if ($ticket->status?->ist_abschluss) {
            return false;
        }

        return $nutzer->rolle === Rolle::Admin
            || $ticket->assigned_to === null
            || $ticket->assigned_to === $nutzer->getKey();
    }
}
