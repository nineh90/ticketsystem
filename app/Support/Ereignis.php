<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Ein einzelner Eintrag im Ereignisstrom.
 *
 * Bewusst kein Model: der Strom setzt sich aus fünf Tabellen zusammen
 * (Protokoll, Kommentare, Zeiten, Anhänge, Dokumente). Ein gemeinsames Model
 * dafür gäbe es nur um den Preis einer weiteren Tabelle, die dieselben Daten
 * ein zweites Mal hält — und die dann bei jedem vergessenen Schreibpfad
 * auseinanderläuft.
 */
readonly class Ereignis
{
    public const KOMMENTAR = 'kommentar';

    public const AENDERUNG = 'aenderung';

    public const ANGELEGT = 'angelegt';

    public const ZEIT = 'zeit';

    public const ANHANG = 'anhang';

    /**
     * Ein Kunde hat auf ein Angebot geantwortet.
     *
     * Der einzige Typ ohne Ticket. Er steht trotzdem hier und nicht in einer
     * eigenen Liste: eine Angebotszusage ist genau die Art Nachricht, für die
     * man den Ticker offen hat — und wer sie in einer zweiten Karte suchen
     * muss, erfährt sie am nächsten Tag.
     */
    public const DOKUMENT = 'dokument';

    /**
     * @param  array<int, string>  $zeilen  Detailzeilen, etwa "Status: Offen → In Arbeit"
     */
    public function __construct(
        public string $typ,
        public Carbon $zeitpunkt,
        public ?Ticket $ticket,
        public ?User $nutzer,
        public string $was,
        public array $zeilen = [],
        public ?string $zitat = null,
        public bool $intern = false,
        /**
         * Worauf sich der Eintrag bezieht, wenn es kein Ticket ist — heute
         * der Kunde eines Dokuments. Ohne das stünde eine Angebotszusage
         * ohne jeden Hinweis darauf da, um wen es geht.
         */
        public ?string $kontext = null,
        public ?string $kontextUrl = null,
    ) {}

    /** Wer es getan hat. Leer heißt: über die Schnittstelle, nicht von Hand. */
    public function urheber(): string
    {
        return $this->nutzer?->name ?? 'System / Schnittstelle';
    }

    public function icon(): string
    {
        return match ($this->typ) {
            self::KOMMENTAR => 'heroicon-o-chat-bubble-left-right',
            self::ANGELEGT => 'heroicon-o-sparkles',
            self::ZEIT => 'heroicon-o-clock',
            self::ANHANG => 'heroicon-o-paper-clip',
            self::DOKUMENT => 'heroicon-o-document-check',
            default => 'heroicon-o-arrow-path',
        };
    }

    /**
     * Die Farbe des Punktes am linken Rand. Sie ist die einzige Möglichkeit,
     * in einer langen Liste auf einen Blick zu erkennen, was für ein Ereignis
     * das ist, ohne jede Zeile zu lesen.
     */
    public function farbe(): string
    {
        return match ($this->typ) {
            self::KOMMENTAR => 'primary',
            self::ANGELEGT => 'success',
            self::ZEIT => 'info',
            self::ANHANG => 'warning',
            self::DOKUMENT => 'success',
            default => 'gray',
        };
    }

    public function istNeu(?Carbon $seit): bool
    {
        return $seit !== null && $this->zeitpunkt->greaterThan($seit);
    }

    /** Der Tag, unter dem der Eintrag einsortiert wird. */
    public function tag(): string
    {
        return match (true) {
            $this->zeitpunkt->isToday() => 'Heute',
            $this->zeitpunkt->isYesterday() => 'Gestern',
            default => $this->zeitpunkt->translatedFormat('D, d.m.Y'),
        };
    }
}
