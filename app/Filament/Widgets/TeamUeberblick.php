<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Raster;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Der Blick aufs Ganze — nur für Administratoren.
 *
 * MeinUeberblick beantwortet "was muss ich heute tun". Diese Zahlen
 * beantworten die andere Frage: was läuft gerade, unabhängig davon, wem es
 * zugewiesen ist.
 *
 * Bewusst nicht für Mitarbeiter: acht Kacheln über der eigentlichen Arbeit
 * sind keine Übersicht mehr, sondern eine Wand aus Zahlen. Wer an einem
 * Ticket sitzt, braucht oben seine vier — was er offen hat, was diese Woche
 * fällig ist, was frei herumliegt und wie viel Zeit er gebucht hat. Wie
 * ausgelastet das Projekt insgesamt ist, steht im Diagramm unten.
 *
 * Die Abfragen laufen trotzdem über sichtbarFuer statt über ungefilterte
 * Gesamtsummen — sonst hinge die Rollentrennung dieses Widgets allein an
 * canView(), und die wäre beim nächsten "zeig das doch auch dem Team"
 * lautlos weg.
 */
class TeamUeberblick extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /** Steht neben MeinUeberblick, deshalb halbe Breite und zwei Kacheln je Reihe. */
    protected int|string|array $columnSpan = Raster::HALB;

    protected int|array|null $columns = ['default' => 4, 'xl' => 2];

    /** Wie lange ein offenes Ticket ruhen darf, bevor es auffällt. */
    private const RUHEND_AB_TAGEN = 3;

    protected ?string $heading = 'Im Betrieb';

    public static function canView(): bool
    {
        return auth()->user()?->istAdmin() ?? false;
    }

    protected function getStats(): array
    {
        /** @var User $nutzer */
        $nutzer = auth()->user();

        $sichtbar = fn (): Builder => Ticket::query()->sichtbarFuer($nutzer);

        $offen = $sichtbar()->offen()->count();

        $ueberfaellig = $sichtbar()
            ->offen()
            ->whereDate('faellig_am', '<', today())
            ->count();

        // Was offen ist und noch niemandem gehört. Steht als Beschreibung an
        // der Gesamtzahl statt in einer eigenen Kachel: es ist kein zweiter
        // Sachverhalt, sondern ein Teil derselben Menge.
        $unzugewiesen = $sichtbar()
            ->offen()
            ->whereNull('assigned_to')
            ->count();

        $neuHeute = $sichtbar()
            ->whereDate('created_at', today())
            ->count();

        $erledigtHeute = $sichtbar()
            ->whereDate('erledigt_at', today())
            ->count();

        // Ruhend heißt: seit Tagen nichts geändert UND niemand hat etwas dazu
        // geschrieben. Nur auf updated_at zu schauen reichte nicht — ein
        // Ticket, unter dem heute diskutiert wurde, gilt nicht als liegen
        // geblieben, auch wenn niemand ein Feld angefasst hat.
        $grenze = now()->subDays(self::RUHEND_AB_TAGEN);

        $ruhend = $sichtbar()
            ->offen()
            ->where('updated_at', '<', $grenze)
            ->whereDoesntHave('comments', fn (Builder $q) => $q->where('created_at', '>=', $grenze))
            ->count();

        // Bewusst die Zeit aller Beteiligten, nicht nur die eigene: die eigene
        // steht schon nebenan in MeinUeberblick. Hier geht es darum, was
        // heute insgesamt in diese Projekte geflossen ist.
        $minutenHeute = TimeEntry::query()
            ->whereIn('ticket_id', $sichtbar()->select('tickets.id'))
            ->whereDate('gestartet_am', today())
            ->sum('minuten');

        return [
            Stat::make('Offen gesamt', (string) $offen)
                ->description(
                    ($ueberfaellig > 0 ? "{$ueberfaellig} überfällig" : 'nichts überfällig')
                    .', '.$unzugewiesen.' nicht zugeteilt',
                )
                ->descriptionIcon($ueberfaellig > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($ueberfaellig > 0 ? 'danger' : 'success'),

            Stat::make('Heute eingegangen', (string) $neuHeute)
                ->description($erledigtHeute.' heute erledigt')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color($neuHeute > 0 ? 'primary' : 'gray'),

            Stat::make('Liegt seit '.self::RUHEND_AB_TAGEN.' Tagen', (string) $ruhend)
                ->description('offen, ohne Änderung und ohne Kommentar')
                ->descriptionIcon('heroicon-m-moon')
                ->color($ruhend > 0 ? 'warning' : 'gray'),

            Stat::make('Zeit heute', $this->alsStunden((int) $minutenHeute))
                ->description('von allen Beteiligten erfasst')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }

    private function alsStunden(int $minuten): string
    {
        return intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
    }
}
