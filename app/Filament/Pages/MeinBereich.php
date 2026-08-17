<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MeineNachrichten;
use App\Filament\Widgets\MeineTickets;
use App\Filament\Widgets\MeineUhr;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\VonKunden;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Die Startseite: was heute an mir hängt.
 *
 * Vorher stand alles auf einer Seite — die eigenen Zahlen neben den Zahlen des
 * Betriebs, die eigene Arbeitsliste neben dem Geschehen aller. Das liest sich
 * beim ersten Mal wie eine Übersicht und ab dem zweiten wie eine Wand: bei
 * keiner Zahl ist auf den ersten Blick klar, ob sie mich meint oder das
 * Ganze, und die Frage "was mache ich als Nächstes" beantwortet die Hälfte
 * davon gar nicht.
 *
 * Die Trennlinie ist deshalb nicht thematisch, sondern eine Frage: kann ich
 * daran etwas tun? Meine Tickets, meine laufende Uhr, ungelesene Nachrichten
 * und wartende Kundenanliegen — ja. Wie viele Stunden das Team diese Woche
 * gebucht hat — nein, das ist Betrieb und steht nebenan.
 *
 * Kundenanliegen stehen bewusst hier und nicht unter Betrieb, obwohl sie
 * nicht mir zugewiesen sind: ein Kunde, der wartet, ist kein Kennwert,
 * sondern eine offene Antwort. Die gehört auf die Seite, die man morgens
 * ohnehin öffnet.
 */
class MeinBereich extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'Mein Bereich';

    protected static ?string $navigationLabel = 'Mein Bereich';

    /** Erster Punkt der Navigation — die Seite liegt auf der Wurzel. */
    protected static ?int $navigationSort = -2;

    public function getSubheading(): ?string
    {
        return 'Was heute an dir hängt. Zahlen zum Ganzen stehen unter Betrieb.';
    }

    public function getWidgets(): array
    {
        return [
            VonKunden::class,
            MeinUeberblick::class,
            MeineUhr::class,
            MeineNachrichten::class,
            MeineTickets::class,
        ];
    }
}
