<?php

namespace App\Filament\Kunde\Pages;

use App\Filament\Concerns\SchreibtMitUns;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Der kurze Draht zu uns — ohne Anliegen, ohne Ticketnummer.
 *
 * Die Seite hat bewusst keine Liste: der Kunde hat genau einen Verlauf mit
 * uns, und eine Liste mit einem Eintrag ist ein Klick, der nichts entscheidet.
 * Er kommt her und schreibt.
 *
 * Der Unterschied zu "Anliegen melden" ist der, den ein Kunde von sich aus
 * macht: ein Anliegen ist Arbeit, die verfolgt wird und einen Stand hat. Eine
 * Frage nach einem Termin ist keine — sie hier zu stellen, kostet ihn nichts
 * und uns keine Zeile in der Ticketliste.
 */
class Nachrichten extends Page
{
    use SchreibtMitUns;

    /**
     * Kein eigener Menüpunkt mehr (NID-23): der Verlauf steht auf der
     * Kontaktseite, dort wo der Kunde ohnehin hingeht, wenn er uns etwas
     * sagen will. Die Seite selbst bleibt, weil Glockenmeldungen und Mails
     * ihre Adresse tragen — ein "Ansehen"-Knopf soll auch in einem halben
     * Jahr noch etwas öffnen.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Nachrichten';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'nachrichten';

    protected string $view = 'filament.kunde.pages.nachrichten';

    public function mount(): void
    {
        // Der Verlauf entsteht beim ersten Öffnen. Aus unserer Liste bleibt
        // er heraus, solange nichts darin steht (scopeBegonnen) — es entsteht
        // hier also kein leerer Eintrag, den jemand bei uns wegräumen müsste.
        $this->verlauf()->alsGelesenMarkieren(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Nachrichten';
    }

    public function getSubheading(): ?string
    {
        return 'Für alles, was kein Anliegen ist — eine Frage, ein Termin, ein kurzer Hinweis. '
            .'Wir antworten '.config('kontakt.reaktionszeit').'.';
    }
}
