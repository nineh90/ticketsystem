<?php

namespace App\Filament\Kunde\Pages;

use App\Models\Nachricht;
use App\Models\Unterhaltung;
use App\Support\Unterhaltungen;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

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
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Nachrichten';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'nachrichten';

    protected string $view = 'filament.kunde.pages.nachrichten';

    /** Was im Eingabefeld steht — siehe views/filament/unterhaltung.blade.php. */
    public string $entwurf = '';

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

    public static function getNavigationBadge(): ?string
    {
        $offen = Unterhaltungen::ungelesen();

        return $offen > 0 ? (string) $offen : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    /**
     * Der eine Verlauf dieses Kunden.
     *
     * Ohne Merker, damit eine gerade gesendete Nachricht im selben Aufbau
     * schon dabei ist.
     */
    public function verlauf(): Unterhaltung
    {
        $nutzer = auth()->user();

        // Ein Kundenzugang ohne Kundenzuordnung kommt gar nicht erst ins
        // Panel (User::canAccessPanel). Die Prüfung steht trotzdem hier:
        // ohne sie hinge die Zuordnung des Verlaufs an einer Bedingung, die
        // eine ganz andere Datei stellt.
        abort_if($nutzer?->customer_id === null, 403);

        return Unterhaltungen::fuerKunden($nutzer->customer_id)
            ->load(['nachrichten.absender', 'teilnehmer']);
    }

    public function senden(): void
    {
        $unterhaltung = $this->verlauf();

        if (auth()->user()?->cannot('schreiben', $unterhaltung)) {
            throw ValidationException::withMessages([
                'entwurf' => 'In diese Unterhaltung dürfen Sie nicht schreiben.',
            ]);
        }

        $text = trim($this->entwurf);

        if ($text === '') {
            return;
        }

        Nachricht::create([
            'unterhaltung_id' => $unterhaltung->getKey(),
            'user_id' => auth()->id(),
            'text' => $text,
        ]);

        $this->entwurf = '';
    }
}
