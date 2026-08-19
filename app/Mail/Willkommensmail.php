<?php

namespace App\Mail;

use App\Enums\MailEreignis;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die erste Mail an eine frisch bestätigte Adresse.
 *
 * Sie hat einen handfesten Zweck und ist keine Höflichkeit: sie beweist dem
 * Kunden, dass der Weg wirklich funktioniert. Ohne sie bleibt es nach dem
 * Klick still, und er weiß bis zum ersten Ereignis nicht, ob es geklappt hat
 * — bei einem Betreuungskunden können das Wochen sein.
 *
 * Sie sagt außerdem, worüber er künftig hört. Das ist die Gelegenheit, an der
 * er merkt, dass er etwas Falsches angehakt hat.
 */
class Willkommensmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $empfaenger) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sie sind eingetragen');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.willkommen',
            text: 'mail.willkommen-text',
            with: [
                'name' => $this->empfaenger->name,
                'firma' => $this->empfaenger->customer?->name,
                'adresse' => $this->empfaenger->benachrichtigungs_email,
                'themen' => $this->themen(),
                'bereich' => url('/kunde'),
            ],
        );
    }

    /**
     * Die gewählten Themen in seinen Worten.
     *
     * @return array<int, string>
     */
    private function themen(): array
    {
        $gewaehlt = $this->empfaenger->mail_ereignisse;

        return collect(MailEreignis::fuerKunden())
            ->when($gewaehlt !== null, fn ($f) => $f->filter(
                fn (MailEreignis $e) => in_array($e->value, $gewaehlt, true),
            ))
            ->map(fn (MailEreignis $e) => $e->getKundenLabel())
            ->values()
            ->all();
    }
}
