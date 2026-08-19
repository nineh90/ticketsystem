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
 *
 * Eigene Vorlage statt Laravels Markdown-Bausteinen. Die hätten fünfzehn
 * Dateien nach resources/views/vendor gelegt, von denen wir eine bräuchten —
 * und sie sehen aus wie jede andere Laravel-Mail. Eine einzige Datei mit
 * eingebauten Stilen ist hier weniger, nicht mehr.
 */
class Glockenmeldung extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $farbe  Filaments Farbname aus der Meldung
     *                              (danger, warning, success, info …). Er
     *                              färbt den Streifen über der Mail — dieselbe
     *                              Unterscheidung, die man an der Glocke am
     *                              Punkt am Rand sieht.
     */
    public function __construct(
        public string $titel,
        public ?string $text = null,
        public ?string $url = null,
        public ?string $farbe = null,
        /**
         * Das Logo des Kunden, um den es geht — und sein Name als
         * Ersatztext.
         *
         * Bilder sind in vielen Postfächern erst einmal blockiert. Das Logo
         * darf deshalb nichts tragen, was nicht auch ohne es dasteht: der
         * Kundenname steht ohnehin schon im Betreff ("Fehler von Sarah
         * Schweikert"). Es ist eine Hilfe beim Überfliegen, keine Information.
         */
        public ?string $kundenLogo = null,
        public ?string $kundenName = null,
        /**
         * Ob der Empfänger ein Kundenzugang ist.
         *
         * Steuert einzig die Fußzeile: wo man den Versand wieder abschaltet,
         * ist innen und außen eine andere Seite. Alles darüber — Betreff,
         * Text, Knopf — ist für beide dasselbe, weil es dieselbe Meldung ist.
         */
        public bool $fuerKunden = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titel);
    }

    /**
     * HTML **und** Text. Die Textfassung ist keine Höflichkeit: manche
     * Postfächer zeigen sie in der Vorschau, und wer Bilder und HTML
     * abgeschaltet hat, sieht sonst eine leere Mail.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.glockenmeldung',
            text: 'mail.glockenmeldung-text',
        );
    }
}
