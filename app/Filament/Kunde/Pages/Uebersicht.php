<?php

namespace App\Filament\Kunde\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Die Startseite des Kundenbereichs.
 *
 * Filaments Dashboard, aber mit eigenem Namen und eigener Adresse: "Dashboard"
 * ist ein Wort aus unserer Welt. Wer sich hier anmeldet, kommt auf eine
 * "Übersicht".
 */
class Uebersicht extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Übersicht';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = '/';

    public function getTitle(): string
    {
        return 'Übersicht';
    }

    /**
     * Bewusst leer: Begrüßung und Firmenname stehen im Willkommen-Widget,
     * dort zusammen mit dem Logo des Kunden. Zweimal "Guten Tag" auf
     * derselben Seite liest sich wie ein Fehler.
     *
     * Auch der Knopf "Etwas melden" stand hier. Seit das Formular selbst auf
     * der Übersicht liegt (Widgets\EtwasMelden), führte er auf eine zweite
     * Fassung dessen, was drei Zeilen tiefer schon steht.
     */
    public function getHeading(): string
    {
        return '';
    }
}
