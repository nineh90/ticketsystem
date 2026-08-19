<?php

namespace App\Filament\Widgets;

use App\Enums\MailEreignis;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * Die Frage, ob ich Mails bekommen will — einmal, sichtbar.
 *
 * Dasselbe Muster wie BenachrichtigungenEinrichten im Kundenbereich, und aus
 * demselben Grund: niemand geht von sich aus in eine Einstellung, von deren
 * Existenz er nichts weiß. Die Wache ist die Seite, auf der man ohnehin
 * landet.
 *
 * Sie verschwindet, sobald geantwortet wurde — auch bei "nein". Ein Hinweis,
 * der nach der Entscheidung stehen bleibt, ist eine Aufforderung.
 *
 * **Beide Knöpfe beantworten die Frage.** Das ist der Punkt: "Später" gibt es
 * nicht, denn ein "später" bedeutet, dass die Karte morgen wieder dasteht —
 * und übermorgen liest sie niemand mehr, obwohl sie noch da ist.
 */
class MailEinrichten extends Widget
{
    protected string $view = 'filament.widgets.mail-einrichten';

    /** Ganz oben — sie ist eine Frage, keine Kennzahl. */
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Ob in diesem Seitenaufbau schon geantwortet wurde.
     *
     * canView() läuft nur, wenn die Seite aufgebaut wird — nach einem Klick
     * stünde die Frage sonst weiter da, obwohl sie beantwortet ist, und das
     * sieht aus, als hätte der Knopf nichts getan. Beim nächsten Aufruf hält
     * canView() die Karte dann dauerhaft draußen.
     */
    public bool $beantwortet = false;

    public static function canView(): bool
    {
        $nutzer = auth()->user();

        if ($nutzer === null || $nutzer->istKunde()) {
            return false;
        }

        return $nutzer->mussNochEntscheiden();
    }

    /**
     * Ja — mit der Vorgabe: alles, was hereinkommt.
     *
     * Nicht "alles überhaupt": über das, was wir selbst nach außen schicken,
     * braucht niemand eine Mail, er war es meistens selbst
     * (MailEreignis::vorgabeIntern).
     */
    public function ja(): void
    {
        $nutzer = $this->nutzer();

        $nutzer->forceFill([
            'mail_benachrichtigungen' => true,
            // Nur setzen, wenn noch nichts dasteht: hat ein Admin die Themen
            // beim Anlegen schon ausgewählt, wäre das Überschreiben genau
            // die stille Änderung, die niemandem auffällt.
            'mail_ereignisse' => $nutzer->mail_ereignisse ?? MailEreignis::vorgabeIntern(),
            'benachrichtigungen_gefragt_at' => now(),
        ])->save();

        Notification::make()
            ->title('Eingeschaltet')
            ->body('Du bekommst jetzt Mail an '.$nutzer->email.'. Welche genau, steht unter "Mein Zugang".')
            ->success()
            ->send();

        $this->beantwortet = true;
    }

    public function nein(): void
    {
        $this->nutzer()->forceFill([
            'mail_benachrichtigungen' => false,
            'benachrichtigungen_gefragt_at' => now(),
        ])->save();

        Notification::make()
            ->title('Alles klar')
            ->body('Es bleibt bei der Glocke. Unter "Mein Zugang" kannst du das jederzeit ändern.')
            ->send();

        $this->beantwortet = true;
    }

    public function kontoUrl(): string
    {
        return Filament::getProfileUrl() ?? '#';
    }

    /** @return array<int, string> Die Themen als Worte, für die Karte. */
    public function themen(): array
    {
        return collect(MailEreignis::cases())
            ->filter(fn (MailEreignis $e) => $e->nachInnen())
            ->map(fn (MailEreignis $e) => $e->getLabel())
            ->values()
            ->all();
    }

    private function nutzer(): User
    {
        /** @var User $nutzer */
        $nutzer = auth()->user();

        return $nutzer;
    }
}
