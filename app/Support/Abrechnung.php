<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Was noch abzurechnen ist.
 *
 * An einer Stelle, weil dieselbe Frage an drei Orten gestellt wird: auf der
 * Abrechnungsseite, in der Kundenakte und in der Aktion, die Zeiten einer
 * Rechnung zuordnet. Dreimal formuliert wäre sie spätestens dann dreierlei,
 * wenn jemand entscheidet, dass laufende Uhren doch mitzählen sollen.
 *
 * Zwei Regeln stecken darin, und beide sind bewusst gewählt:
 *
 *  - Welche Buchungen überhaupt zählen, sagt TimeEntry::offenZumAbrechnen.
 *  - Wer welche sehen darf, sagt Ticket::sichtbarFuer — dieselbe Abfrage wie
 *    in jeder Liste. Ein Mitarbeiter sieht die offene Zeit seiner Kunden,
 *    nicht die aller. Das ist eine Entscheidung und keine Nachlässigkeit: in
 *    einem Zweimannbetrieb ist "bei Sarah liegen acht Stunden" eine
 *    Arbeitsinformation, keine Kontostandsauskunft. Soll es strenger sein,
 *    genügt hier ein istAdmin() — die Seite und die Aktion gehen beide durch
 *    diese Klasse.
 */
class Abrechnung
{
    /**
     * Offene abrechenbare Zeit je Kunde, absteigend nach Menge.
     *
     * @return Collection<int, object{kunde: Customer, minuten: int, buchungen: int, aeltester: ?Carbon}>
     */
    public static function jeKunde(User $nutzer): Collection
    {
        $zeilen = self::basis($nutzer)
            ->groupBy('tickets.customer_id')
            ->selectRaw('tickets.customer_id as kunde_id, sum(time_entries.minuten) as minuten, count(*) as buchungen, min(time_entries.gestartet_am) as aeltester')
            // Nullen fallen weg: ein Kunde ohne offene Zeit gehört nicht auf
            // eine Liste, die "was ist noch abzurechnen" beantwortet.
            ->havingRaw('sum(time_entries.minuten) > 0')
            ->get();

        if ($zeilen->isEmpty()) {
            return collect();
        }

        $kunden = Customer::query()
            ->whereKey($zeilen->pluck('kunde_id'))
            ->get()
            ->keyBy('id');

        return $zeilen
            ->map(fn (object $zeile) => (object) [
                'kunde' => $kunden[$zeile->kunde_id] ?? null,
                'minuten' => (int) $zeile->minuten,
                'buchungen' => (int) $zeile->buchungen,
                'aeltester' => $zeile->aeltester ? Carbon::parse($zeile->aeltester) : null,
            ])
            ->filter(fn (object $zeile) => $zeile->kunde !== null)
            ->sortByDesc('minuten')
            ->values();
    }

    /** Offene abrechenbare Minuten eines einzelnen Kunden. */
    public static function minutenFuer(Customer $kunde, User $nutzer): int
    {
        return (int) self::basis($nutzer)
            ->where('tickets.customer_id', $kunde->getKey())
            ->sum('time_entries.minuten');
    }

    /**
     * Die einzelnen offenen Buchungen eines Kunden — für die Zuordnung.
     *
     * Als Eloquent-Abfrage und nicht als fertige Sammlung: die Aktion hängt
     * noch eine Datumsgrenze an ("nur bis zum Rechnungsdatum"), und die
     * gehört dorthin, wo sie gewählt wird.
     *
     * @return Builder<TimeEntry>
     */
    public static function buchungenFuer(Customer $kunde, User $nutzer): Builder
    {
        return TimeEntry::query()
            ->offenZumAbrechnen()
            ->whereIn('ticket_id', Ticket::query()
                ->sichtbarFuer($nutzer)
                ->where('customer_id', $kunde->getKey())
                ->select('tickets.id'))
            ->with(['ticket', 'user'])
            ->orderBy('gestartet_am');
    }

    /**
     * Der gemeinsame Unterbau: offene Buchungen, auf sichtbare Tickets
     * beschränkt, mit den Tickets verbunden — damit nach Kunde gruppiert
     * werden kann.
     *
     * Die Abfrage geht von den Zeiten aus und verbindet die Tickets dazu,
     * nicht umgekehrt: gezählt werden Buchungen, und ein Ticket ohne offene
     * Zeit soll gar nicht erst mitkommen.
     */
    private static function basis(User $nutzer): Builder
    {
        return TimeEntry::query()
            ->offenZumAbrechnen()
            ->join('tickets', 'tickets.id', '=', 'time_entries.ticket_id')
            ->whereIn('time_entries.ticket_id', Ticket::query()
                ->sichtbarFuer($nutzer)
                ->select('tickets.id'));
    }
}
