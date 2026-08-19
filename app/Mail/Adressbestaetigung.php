<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Bitte bestätigen Sie diese Adresse."
 *
 * Die einzige Mail, die an eine unbestätigte Adresse geht — und deshalb die
 * einzige, die keinerlei Inhalt trägt: kein Ticket, kein Projekt, kein
 * Betrag. Landet sie beim Falschen, erfährt er nur, dass es bei uns ein
 * Ticketsystem gibt und dass jemand seine Adresse eingetragen hat. Genau
 * dafür ist der Bestätigungsschritt da.
 */
class Adressbestaetigung extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $empfaenger,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bitte bestätigen Sie Ihre E-Mail-Adresse');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.adressbestaetigung',
            text: 'mail.adressbestaetigung-text',
        );
    }
}
