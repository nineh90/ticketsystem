<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Sichtbarkeit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Der Blick aufs Ganze — jeder auf seinen Ausschnitt davon.
 *
 * MeinUeberblick beantwortet "was muss ich heute tun". Diese Zahlen
 * beantworten die andere Frage: was läuft gerade, unabhängig davon, wem es
 * zugewiesen ist. Ein Ticket, das seit vier Tagen unbeachtet in meinem
 * Projekt liegt, taucht in keiner persönlichen Liste auf — hier schon.
 *
 * Der Ausschnitt ist die übliche Rollenregel: der Administrator sieht den
 * Betrieb, ein Mitarbeiter seine Projekte. Deshalb läuft jede Zahl über
 * sichtbarFuer und nicht über eine ungefilterte Gesamtsumme.
 */
class TeamUeberblick extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /** Steht neben MeinUeberblick, deshalb halbe Breite und zwei Kacheln je Reihe. */
    protected int|string|array $columnSpan = 1;

    protected int|array|null $columns = 2;

    /** Wie lange ein offenes Ticket ruhen darf, bevor es auffällt. */
    private const RUHEND_AB_TAGEN = 3;

    /**
     * Wer keinem Kunden und keinem Projekt zugeordnet ist, bekommt hier vier
     * Nullen ohne erkennbaren Grund. Die Erklärung dazu steht schon in
     * MeinUeberblick; ein zweites Mal danebengestellt wäre sie nur Lärm.
     */
    public static function canView(): bool
    {
        return auth()->check() && ! Sichtbarkeit::ohneProjekte();
    }

    public function getHeading(): ?string
    {
        return auth()->user()?->istAdmin() ? 'Im Betrieb' : 'In meinen Projekten';
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
                ->description($ueberfaellig > 0 ? "{$ueberfaellig} davon überfällig" : 'nichts überfällig')
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
