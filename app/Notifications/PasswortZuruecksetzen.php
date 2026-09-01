<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Die Mail hinter "Passwort vergessen" — ohne Warteschlange.
 *
 * Wortgleich mit Filament\Auth\Notifications\ResetPassword, mit genau einem
 * Unterschied: dort steht "implements ShouldQueue", hier nicht.
 *
 * Das ist derselbe Fallstrick, in den dieses Projekt schon einmal getreten
 * ist (siehe Benachrichtigung::zustellen): QUEUE_CONNECTION steht auf
 * "database", und einen Worker gibt es nirgends — weder lokal noch im
 * Container auf dem Server, der ausschließlich "schedule:work" hält. Eine
 * Meldung mit ShouldQueue landet damit in der jobs-Tabelle und bleibt dort
 * liegen. Kein Fehler, keine Meldung, nichts im Protokoll; sie kommt einfach
 * nie an.
 *
 * Für eine Reset-Mail wäre das die schlechteste aller Fehlerarten. Wer sie
 * anfordert, steht in diesem Moment ausgesperrt vor der Anmeldung und wartet
 * auf sie — und der Knopf wäre wieder das leere Versprechen, das er bis zum
 * 01.09.2026 aus einem anderen Grund war.
 *
 * Sofort zu senden ist hier auch sachlich richtig und nicht bloß ein
 * Ausweichen: es ist eine einzelne Mail, ausgelöst von einem Menschen, der
 * darauf wartet. Sie in eine Warteschlange zu legen, verzögert sie
 * bestenfalls um eine Minute und bringt dafür einen zweiten Dauerprozess mit,
 * der ausfallen kann.
 *
 * Eingehängt wird sie in AppServiceProvider::register() — Filament löst seine
 * eigene Klasse über den Container auf, und genau dort wird sie ersetzt.
 */
class PasswortZuruecksetzen extends ResetPassword
{
    /**
     * Das fertige Ziel, gesetzt von Filament.
     *
     * Filament baut die Adresse selbst (Filament::getResetPasswordUrl) und
     * schreibt sie nach dem Erzeugen in dieses Feld — nur so trägt der Link
     * das richtige Panel. Ohne die Überschreibung von resetUrl() unten
     * erzeugte Laravel stattdessen seine eigene, die auf eine Route namens
     * "password.reset" zeigt; die gibt es hier nicht, und ein Kunde landete
     * im Zweifel an der internen Anmeldung, die seine Daten abweist.
     */
    public string $url;

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }

    /**
     * Die Mail selbst — deutsch, und in der Bauart der übrigen.
     *
     * Laravels Vorlage baut sie aus Lang::get('Reset your password') und
     * Geschwistern. Die Anwendung läuft auf APP_LOCALE=en und hat kein
     * lang/-Verzeichnis, also kommt dort wörtlich Englisch heraus — ein
     * englischer Brief an einen deutschen Kunden, ausgerechnet in dem Moment,
     * in dem er ausgesperrt vor der Tür steht.
     *
     * Den Weg über APP_LOCALE=de und übersetzte Sprachdateien gehen wir
     * bewusst nicht: das legte die Übersetzung sämtlicher Framework-Texte
     * (Validierung, Paginierung, Datumsformate) mit an, und die halbe
     * Übersetzung ist schlechter als gar keine. Diese eine Mail schreiben wir
     * selbst, wie Adressbestaetigung und Willkommensmail auch — gleiche
     * Ansichten, gleiche Farben, gleicher Ton.
     */
    public function toMail($notifiable): MailMessage
    {
        $kunde = $notifiable instanceof User && $notifiable->istKunde();

        return (new MailMessage)
            ->subject('Ihr Zugang: neues Passwort vergeben')
            ->view(
                ['mail.passwort-zuruecksetzen', 'mail.passwort-zuruecksetzen-text'],
                [
                    'url' => $this->url,
                    // Nur der Vorname, wie im Willkommen-Widget. "Guten Tag,
                    // Frau Schweikert" wäre steif; wir stehen mit jedem
                    // persönlich in Kontakt.
                    'vorname' => str($notifiable->name ?? '')->before(' ')->toString(),
                    // Kunden werden gesiezt, wir untereinander nicht — die
                    // Unterscheidung zieht sich durch das ganze System, siehe
                    // Anmeldung::istKundenbereich().
                    'siezen' => $kunde,
                    // Aus der Konfiguration statt fest eingetippt: steht in
                    // config/auth.php und ist dort auf 60 Minuten gesetzt.
                    'minuten' => (int) config(
                        'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                    ),
                ],
            );
    }
}
