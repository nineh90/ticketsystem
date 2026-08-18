<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\TimeEntry;
use App\Support\Dauer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * Die vier Zahlen zu einem Kunden.
 *
 * Was man wissen will, bevor man mit jemandem telefoniert: liegt bei ihm
 * etwas offen, wie viel Zeit ist bisher hineingegangen, und schuldet er uns
 * Geld. Bis hierher stand davon nirgends etwas — die Kundenakte war ein
 * Formular mit Stammdaten, und alles Übrige musste man sich aus drei Listen
 * zusammensuchen.
 *
 * Alle Zahlen laufen über die Beziehungen des Kunden und damit über einen
 * Datensatz, den der Betrachter ohnehin sehen darf: wer diese Seite öffnet,
 * ist an der CustomerPolicy und an CustomerResource::getEloquentQuery
 * vorbeigekommen. Ein zweiter Filter hier wäre eine zweite Formulierung
 * derselben Regel.
 */
class KundeKennzahlen extends StatsOverviewWidget
{
    /** Wird von der Seite gesetzt (InteractsWithRecord::getWidgetData). */
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        /** @var Customer $kunde */
        $kunde = $this->record;

        $offen = $kunde->tickets()->offen()->count();
        $ueberfaellig = $kunde->tickets()->ueberfaellig()->count();
        $erledigt = $kunde->tickets()->whereNotNull('erledigt_at')->count();

        // Zeit über die Tickets des Kunden, nicht über ein eigenes Feld:
        // gebucht wird immer an einem Ticket, und tickets.customer_id ist
        // gesetzt (siehe Migration).
        $minuten = (int) TimeEntry::query()
            ->whereIn('ticket_id', $kunde->tickets()->select('tickets.id'))
            ->sum('minuten');

        $minutenJahr = (int) TimeEntry::query()
            ->whereIn('ticket_id', $kunde->tickets()->select('tickets.id'))
            ->where('gestartet_am', '>=', now()->startOfYear())
            ->sum('minuten');

        $offeneRechnungen = $kunde->dokumente()
            ->rechnungen()
            ->offen()
            ->get();

        $offenerBetrag = (float) $offeneRechnungen->sum('betrag');
        $ueberfaelligeRechnungen = $offeneRechnungen
            ->filter(fn (Dokument $d) => $d->istUeberfaellig())
            ->count();

        // Auf die Ticketliste, aber schon auf diesen Kunden eingegrenzt —
        // sonst führte eine Zahl, die einen Kunden meint, auf die Tickets
        // aller. Der Filtername "customer" stammt aus TicketsTable, wie die
        // Adresse zusammengesetzt sein muss, steht in listeUrl().
        $nurDieserKunde = ['customer' => ['value' => $kunde->getKey()]];

        return [
            Stat::make('Offene Tickets', (string) $offen)
                ->description($ueberfaellig > 0 ? "{$ueberfaellig} davon überfällig" : 'nichts überfällig')
                ->descriptionIcon($ueberfaellig > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($ueberfaellig > 0 ? 'danger' : 'success')
                ->url(TicketResource::listeUrl('offen', '', $nurDieserKunde)),

            Stat::make('Erledigt insgesamt', (string) $erledigt)
                ->description('seit Beginn der Zusammenarbeit')
                ->descriptionIcon('heroicon-m-check')
                ->color('gray')
                ->url(TicketResource::listeUrl('alle', '', $nurDieserKunde)),

            Stat::make('Erfasste Zeit', Dauer::alsStunden($minuten))
                ->description(Dauer::alsStunden($minutenJahr).' davon in diesem Jahr')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            // Ohne Betrag am Dokument bleibt die Summe bei 0 — das ist
            // richtig so und keine Lücke: eine Rechnung ohne erfassten Betrag
            // ist eine, bei der wir die Zahl nicht wissen, und geraten wird
            // hier nichts.
            Stat::make('Offene Posten', number_format($offenerBetrag, 2, ',', '.').' €')
                ->description($ueberfaelligeRechnungen > 0
                    ? $ueberfaelligeRechnungen.' Rechnung(en) überfällig'
                    : $offeneRechnungen->count().' offene Rechnung(en)')
                ->descriptionIcon($ueberfaelligeRechnungen > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-banknotes')
                ->color($ueberfaelligeRechnungen > 0 ? 'danger' : ($offenerBetrag > 0 ? 'warning' : 'success')),
        ];
    }
}
