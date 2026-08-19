<?php

namespace App\Filament\Kunde\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Die Frage, ob der Kunde informiert werden möchte — einmal, sichtbar.
 *
 * Sie steht auf der Übersicht und nicht versteckt in "Mein Konto", weil
 * niemand von sich aus dorthin geht, um eine Einstellung zu suchen, von deren
 * Existenz er nichts weiß. Die Übersicht ist die Seite, auf der er ohnehin
 * landet.
 *
 * Und sie verschwindet, sobald er geantwortet hat — auch dann, wenn die
 * Antwort "nein" war. Ein Hinweis, der stehen bleibt, nachdem man sich
 * entschieden hat, ist eine Aufforderung, und die will hier niemand stellen.
 * Dafür gibt es benachrichtigungen_gefragt_at.
 *
 * Der Fall dazwischen ist der zweite Grund für die Karte: eine genannte, aber
 * noch nicht bestätigte Adresse. Ohne einen Hinweis darauf wartet jemand
 * wochenlang auf Mail, die nie kommt, weil der Bestätigungslink im Spam liegt.
 */
class BenachrichtigungenEinrichten extends Widget
{
    protected string $view = 'filament.kunde.widgets.benachrichtigungen-einrichten';

    /** Ganz oben, direkt unter der Begrüßung. */
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $nutzer = auth()->user();

        if ($nutzer === null || ! $nutzer->istKunde()) {
            return false;
        }

        return $nutzer->mussNochEntscheiden() || $nutzer->wartetAufAdressbestaetigung();
    }

    /** Noch nie gefragt — oder Adresse genannt und unbestätigt? */
    public function wartetAufBestaetigung(): bool
    {
        return auth()->user()?->wartetAufAdressbestaetigung() ?? false;
    }

    public function adresse(): ?string
    {
        return auth()->user()?->benachrichtigungs_email;
    }

    public function kontoUrl(): string
    {
        return Filament::getProfileUrl() ?? '#';
    }
}
