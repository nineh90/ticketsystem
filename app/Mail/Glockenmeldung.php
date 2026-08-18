<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Dieselbe Meldung wie an der Glocke, nur per Mail.
 *
 * Sie trägt bewusst keinen eigenen Text: Betreff und Inhalt sind genau die,
 * die auch in der Glocke stehen. Zwei Formulierungen für dasselbe Ereignis
 * wären zwei Stellen, die man beim nächsten Umbau beide finden müsste — und
 * die zweite findet man nicht.
 *
 * Die Mail ist ein Hinweis, keine Akte: ein Satz, ein Knopf. Alles Weitere
 * steht im System, und dorthin führt der Knopf.
 */
class Glockenmeldung extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $titel,
        public ?string $text = null,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titel);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.glockenmeldung');
    }
}
