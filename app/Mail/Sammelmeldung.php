<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mehrere Meldungen in einer Mail — siehe Support\Sammelmail.
 *
 * Jede Zeile ist eine Glockenmeldung mit ihrem eigenen Knopf. Wie bei der
 * Glockenmeldung trägt sie keinen eigenen Text: was hier steht, stand vorher
 * schon an der Glocke.
 *
 * Bei nur einer Meldung ist deren Titel der Betreff. Eine Sammelmail, in der
 * eine Zeile steht, soll nicht wie eine Sammelmail aussehen.
 */
class Sammelmeldung extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{titel: string, text: ?string, url: ?string, farbe: ?string, kunde: ?string}>  $posten
     * @param  bool  $fuerKunden  steuert Betreff, Gruppierung und Fußzeile
     */
    public function __construct(
        public array $posten,
        public bool $fuerKunden = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->betreff());
    }

    public function betreff(): string
    {
        $anzahl = count($this->posten);

        if ($anzahl === 1) {
            return $this->posten[0]['titel'];
        }

        return $this->fuerKunden
            ? $anzahl.' Neuigkeiten zu Ihren Anliegen'
            : $anzahl.' neue Meldungen an Bord';
    }

    /**
     * Intern nach Kunden gruppiert — wer zwölf Meldungen liest, will wissen,
     * von wem. Beim Kunden gibt es nur einen, also keine Überschriften.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function gruppen(): array
    {
        if ($this->fuerKunden) {
            return ['' => $this->posten];
        }

        return collect($this->posten)
            ->groupBy(fn (array $p) => $p['kunde'] ?? 'Ohne Kunde')
            ->map(fn ($gruppe) => $gruppe->all())
            ->all();
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sammelmeldung',
            text: 'mail.sammelmeldung-text',
            with: [
                'betreff' => $this->betreff(),
                'gruppen' => $this->gruppen(),
            ],
        );
    }
}
