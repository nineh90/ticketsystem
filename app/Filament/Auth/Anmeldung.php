<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Die Anmeldung beider Panels.
 *
 * Sie unterscheidet einen Fall, den Filament von Haus aus nicht unterscheidet:
 * "Zugangsdaten stimmen nicht" und "Zugangsdaten stimmen, aber das ist der
 * falsche Bereich" sehen sonst gleich aus. Beim ersten Kundenzugang ist genau
 * das passiert — die Daten wurden an der internen Anmeldung eingegeben, dort
 * abgewiesen, und die Meldung las sich wie ein vertipptes Passwort. Der
 * Zugang war die ganze Zeit in Ordnung; gefehlt hat nur der Hinweis, dass er
 * eine Adresse weiter gilt.
 *
 * Die genauere Meldung erscheint ausschließlich, nachdem das Passwort bereits
 * geprüft und für richtig befunden wurde (siehe Login::authenticate: die
 * Panel-Prüfung läuft nach validateCredentials). Wer sie zu sehen bekommt,
 * kennt das Passwort also ohnehin — es wird nichts preisgegeben, was nicht
 * schon bekannt wäre. Bei falschem Passwort bleibt es bei Filaments
 * unspezifischer Meldung, und die Zeitmessung ist unverändert, weil beide
 * Wege an derselben Stelle in dieselbe Ausnahme laufen.
 */
class Anmeldung extends Login
{
    /**
     * Der Bereich, in den dieser Zugang stattdessen gehört — oder null,
     * solange es sich um einen gewöhnlichen Fehlschlag handelt.
     */
    private ?string $andererBereich = null;

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        $erlaubt = parent::isUserAllowedToAccessPanel($user);

        if ($erlaubt || ! $user instanceof User) {
            return $erlaubt;
        }

        // Nur wenn das Konto grundsätzlich in Ordnung ist. Ein gesperrter
        // oder nicht freigegebener Zugang gehört nirgendwo hin, und ihn auf
        // den anderen Bereich zu schicken hieße, ihn dort dasselbe erleben zu
        // lassen — nur eine Seite weiter.
        if (! $user->aktiv || ! $user->panel_zugang) {
            return false;
        }

        $this->andererBereich = $user->istKunde() ? 'kunde' : 'intern';

        return false;
    }

    protected function throwFailureValidationException(): never
    {
        if ($this->andererBereich === null) {
            parent::throwFailureValidationException();
        }

        $kunde = $this->andererBereich === 'kunde';

        // Der Knopf gehört in eine Benachrichtigung und nicht in die
        // Feldmeldung: Validierungstexte werden maskiert ausgegeben, ein Link
        // darin stünde als roher HTML-Schnipsel da.
        Notification::make()
            ->title($kunde ? 'Das ist ein Kundenzugang' : 'Das ist ein interner Zugang')
            ->body($kunde
                ? 'Kundenzugänge melden sich im Kundenbereich an — dort funktionieren dieselben Daten.'
                : 'Interne Zugänge melden sich im internen Bereich an — dort funktionieren dieselben Daten.')
            ->icon('heroicon-o-arrow-right-circle')
            ->warning()
            ->persistent()
            ->actions([
                Action::make('wechseln')
                    ->label($kunde ? 'Zum Kundenbereich' : 'Zum internen Bereich')
                    ->url(route($kunde
                        ? 'filament.kunde.auth.login'
                        : 'filament.admin.auth.login'))
                    ->button(),
            ])
            ->send();

        throw ValidationException::withMessages([
            'data.email' => $kunde
                ? 'Dieser Zugang gilt für den Kundenbereich unter '.route('filament.kunde.auth.login').' — dort bitte anmelden.'
                : 'Dieser Zugang gilt für den internen Bereich unter '.route('filament.admin.auth.login').' — dort bitte anmelden.',
        ]);
    }
}
