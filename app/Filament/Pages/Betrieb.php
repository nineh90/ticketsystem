<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Geschehen;
use App\Filament\Widgets\TeamUeberblick;
use App\Filament\Widgets\TicketsVerteilung;
use App\Filament\Widgets\WerArbeitetGerade;
use App\Filament\Widgets\ZeitenVerteilung;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Was im Ganzen läuft — unabhängig davon, wer es tut.
 *
 * Die Gegenseite zu MeinBereich. Hier steht, was man nicht abarbeitet,
 * sondern im Blick behält: die Zahlen des Betriebs, wer gerade an der Uhr
 * hängt, was sich zuletzt getan hat, wo die Arbeit liegt und wohin die Zeit
 * geflossen ist.
 *
 * Eigene Seite und kein Reiter: die laufenden Uhren sind der Grund. Sie sind
 * die Karte, deretwegen abends noch jemand hinschaut, ob irgendwo etwas
 * mitläuft — und ein Reiter, den man erst anklicken muss, ist genau die Art
 * von Ort, an dem eine vergessene Uhr über Nacht stehen bleibt. Als
 * Menüpunkt mit eigener Adresse kann man die Seite offen liegen lassen.
 *
 * Sichtbar für alle intern, nicht nur für Administratoren. Die Widgets darin
 * entscheiden jeweils selbst: TeamUeberblick zeigt sich nur Administratoren,
 * alles andere läuft für Mitarbeiter durch dieselben Sichtbarkeitsregeln wie
 * überall. Eine Seite, die für Kevin leer ist, wäre trotzdem verwirrend —
 * leer ist sie aber nicht: Geschehen, laufende Uhren und Verteilung zeigen
 * ihm seinen Ausschnitt.
 */
class Betrieb extends Dashboard
{
    protected static string $routePath = 'betrieb';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $title = 'Betrieb';

    protected static ?string $navigationLabel = 'Betrieb';

    /** Direkt hinter "Mein Bereich" und noch vor allen Listen. */
    protected static ?int $navigationSort = -1;

    public function getSubheading(): ?string
    {
        return 'Der Blick aufs Ganze. Was an dir hängt, steht unter Mein Bereich.';
    }

    public function getWidgets(): array
    {
        return [
            TeamUeberblick::class,
            WerArbeitetGerade::class,
            Geschehen::class,
            TicketsVerteilung::class,
            ZeitenVerteilung::class,
        ];
    }
}
