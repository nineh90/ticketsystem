<?php

namespace App\Support;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Dokument;
use App\Models\Meilenstein;
use App\Models\Ticket;
use App\Models\Treffen;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Was in den nächsten Tagen ansteht — aus allem, was im System ein Datum hat.
 *
 * Der Gedanke dahinter: Termine sind längst da, sie liegen nur an vier
 * verschiedenen Stellen. Ein Meilenstein mit Termin ist eine Verabredung, eine
 * Rechnungsfrist ist eine, und ein fälliges Ticket auch — nur sieht man sie
 * erst, wenn man die jeweilige Liste aufmacht. Wer morgens wissen will, was
 * diese Woche liegt, macht vier Listen auf oder keine.
 *
 * **Jede Quelle geht durch ihr eigenes sichtbarFuer.** Das ist der Grund,
 * warum hier nichts zusammengefasst oder abgekürzt wird: eine Vorschau, die
 * "nur schnell" direkt abfragt, ist genau die Stelle, an der ein Mitarbeiter
 * den Kunden eines anderen zu sehen bekommt.
 *
 * Kommt später eine weitere Sorte dazu (ein Urlaub, ein Wartungsfenster),
 * bekommt sie hier eine Methode und taucht damit überall auf, wo die Vorschau
 * steht.
 */
class Wochenplan
{
    /** Wie weit die Vorschau nach vorn schaut, wenn nichts anderes gesagt ist. */
    public const TAGE = 7;

    /**
     * Alles zwischen jetzt und in N Tagen, aufsteigend sortiert.
     *
     * @return Collection<int, Termin>
     */
    public static function fuer(User $nutzer, int $tage = self::TAGE): Collection
    {
        // Ab jetzt und nicht ab Mitternacht: was heute Morgen um neun war,
        // steht heute Nachmittag nicht mehr an. Das Ende dagegen ist ein
        // ganzer Tag — "in sieben Tagen" meint den Tag, nicht die Uhrzeit.
        $von = now();
        $bis = today()->addDays($tage)->endOfDay();

        return collect()
            ->concat(self::treffen($nutzer, $von, $bis))
            ->concat(self::meilensteine($nutzer, $bis))
            ->concat(self::fristen($nutzer, $bis))
            ->concat(self::tickets($nutzer, $bis))
            ->sortBy(fn (Termin $termin) => $termin->zeitpunkt->getTimestamp())
            ->values();
    }

    /**
     * Nach Tagen gruppiert, wie die Vorschau sie ausgibt.
     *
     * Der Schlüssel ist das Datum als Y-m-d — ein Carbon als Schlüssel würde
     * zur Zeichenkette samt Uhrzeit, und dann läge jeder Termin in seiner
     * eigenen Gruppe.
     *
     * @return Collection<string, Collection<int, Termin>>
     */
    public static function jeTag(User $nutzer, int $tage = self::TAGE): Collection
    {
        return self::fuer($nutzer, $tage)
            ->groupBy(fn (Termin $termin) => $termin->zeitpunkt->format('Y-m-d'));
    }

    /** @return Collection<int, Termin> */
    private static function treffen(User $nutzer, Carbon $von, Carbon $bis): Collection
    {
        return Treffen::query()
            ->sichtbarFuer($nutzer)
            ->nichtAbgesagt()
            ->bevorstehend()
            ->where('beginnt_am', '<=', $bis)
            ->with('customer')
            ->get()
            ->map(fn (Treffen $treffen) => new Termin(
                art: Termin::TREFFEN,
                zeitpunkt: $treffen->beginnt_am,
                titel: $treffen->titel,
                kunde: $treffen->customer?->name,
                url: $treffen->customer_id
                    ? CustomerResource::getUrl('view', ['record' => $treffen->customer_id])
                    : null,
                zusatz: $treffen->kunden_sichtbar ? null : 'noch nicht eingeladen',
            ));
    }

    /** @return Collection<int, Termin> */
    private static function meilensteine(User $nutzer, Carbon $bis): Collection
    {
        return Meilenstein::query()
            ->offen()
            ->whereNotNull('faellig_am')
            ->whereBetween('faellig_am', [today(), $bis])
            // Meilensteine haben kein eigenes sichtbarFuer — sie hängen am
            // Projekt, und dessen Regel ist die maßgebliche.
            ->whereHas('project', fn (Builder $q) => $q->sichtbarFuer($nutzer))
            ->with('project.customer')
            ->get()
            ->map(fn (Meilenstein $stein) => new Termin(
                art: Termin::MEILENSTEIN,
                zeitpunkt: $stein->faellig_am->copy()->startOfDay(),
                titel: $stein->titel,
                kunde: $stein->project?->customer?->name,
                url: $stein->project_id
                    ? ProjectResource::getUrl('edit', ['record' => $stein->project_id])
                    : null,
                ganztaegig: true,
                zusatz: $stein->project?->name,
            ));
    }

    /** @return Collection<int, Termin> */
    private static function fristen(User $nutzer, Carbon $bis): Collection
    {
        return Dokument::query()
            ->sichtbarFuer($nutzer)
            ->whereNotNull('faellig_am')
            ->whereBetween('faellig_am', [today(), $bis])
            ->with('customer')
            ->get()
            // Eine bezahlte Rechnung hat keine Frist mehr. Welcher Stand als
            // offen gilt, weiß der Stand selbst (DokumentStand::istOffen) —
            // hier stünde sonst eine zweite Liste, die beim nächsten neuen
            // Stand veraltet.
            ->filter(fn (Dokument $dokument) => $dokument->stand?->istOffen() ?? false)
            ->map(fn (Dokument $dokument) => new Termin(
                art: Termin::FRIST,
                zeitpunkt: $dokument->faellig_am->copy()->startOfDay(),
                titel: $dokument->titel,
                kunde: $dokument->customer?->name,
                url: $dokument->customer_id
                    ? CustomerResource::getUrl('view', ['record' => $dokument->customer_id])
                    : null,
                ganztaegig: true,
                zusatz: $dokument->art?->datumsBeschriftung() ?? 'Frist',
            ));
    }

    /** @return Collection<int, Termin> */
    private static function tickets(User $nutzer, Carbon $bis): Collection
    {
        return Ticket::query()
            ->sichtbarFuer($nutzer)
            ->offen()
            ->whereNotNull('faellig_am')
            ->whereBetween('faellig_am', [today(), $bis])
            ->with('customer')
            ->get()
            ->map(fn (Ticket $ticket) => new Termin(
                art: Termin::TICKET,
                zeitpunkt: $ticket->faellig_am->copy()->startOfDay(),
                titel: $ticket->titel,
                kunde: $ticket->customer?->name,
                url: TicketResource::getUrl('view', ['record' => $ticket]),
                ganztaegig: true,
                zusatz: $ticket->kennung(),
            ));
    }
}
