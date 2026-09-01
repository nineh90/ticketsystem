<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Sie haben jetzt einen Zugang."
 *
 * Die allererste Mail an einen Kunden — und für zwei von fünf Kundenzugängen
 * wäre sie am 01.09.2026 die erste Nachricht überhaupt gewesen, die sie von
 * ihrem Bereich erreicht. Sie trägt deshalb keine Inhalte, sondern genau
 * zwei Dinge: was das hier ist, und einen Knopf.
 *
 * Ausdrücklich ohne Ticketzahlen, Projektstände oder Beträge. Landet sie bei
 * der falschen Adresse — ein Zahlendreher beim Anlegen genügt —, erfährt der
 * Falsche nur, dass es bei uns einen Kundenbereich gibt. Dieselbe Überlegung
 * wie bei der Adressbestaetigung.
 */
class Einladung extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $empfaenger,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ihr Zugang bei Nils-Digital');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.einladung',
            text: 'mail.einladung-text',
            with: [
                // Nur der Vorname — wie im Willkommen-Widget und in der
                // Passwortmail. Wir stehen mit jedem persönlich in Kontakt.
                'vorname' => str($this->empfaenger->name)->before(' ')->toString(),
                'firma' => $this->empfaenger->customer?->name,
                'url' => $this->url,
                // Der Weg, wenn der Link doch einmal abgelaufen ist.
                // Steht in der Mail, weil die Alternative ein Anruf wäre.
                'anmeldung' => url('/kunde/login'),
                // Aus der Konfiguration und nicht als Zahl im Text: die Frist
                // steht in config/auth.php am Broker "kunde", und wenn sie
                // dort geändert wird, soll die Mail nicht weiter etwas
                // anderes behaupten.
                'tage' => (int) round(
                    (int) config('auth.passwords.kunde.expire') / 60 / 24,
                ),
            ],
        );
    }
}
