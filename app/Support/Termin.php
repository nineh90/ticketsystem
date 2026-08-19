<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ein datierter Eintrag in der Wochenvorschau.
 *
 * Bewusst kein Model — dieselbe Überlegung wie beim Ereignis: die Vorschau
 * setzt sich aus mehreren Tabellen zusammen (Treffen, Meilensteine,
 * Dokumentfristen, fällige Tickets). Ein gemeinsames Model dafür gäbe es
 * nur um den Preis einer weiteren Tabelle, die dieselben Daten ein zweites
 * Mal hält und beim ersten vergessenen Schreibpfad auseinanderläuft.
 *
 * Wer eine weitere Sorte Termin aufnehmen will, schreibt eine Methode in
 * Wochenplan und gibt hier Termine zurück. Sonst ändert sich nichts.
 */
readonly class Termin
{
    public const TREFFEN = 'treffen';

    public const MEILENSTEIN = 'meilenstein';

    public const FRIST = 'frist';

    public const TICKET = 'ticket';

    /**
     * @param  bool  $ganztaegig  Ein Meilenstein ist auf den Tag genau
     *                            geplant, ein Treffen auf die Minute. Die
     *                            Vorschau zeigt deshalb bei dem einen eine
     *                            Uhrzeit und beim anderen keine — eine
     *                            erfundene "00:00" wäre eine Aussage, die
     *                            niemand getroffen hat.
     */
    public function __construct(
        public string $art,
        public Carbon $zeitpunkt,
        public string $titel,
        public ?string $kunde = null,
        public ?string $url = null,
        public bool $ganztaegig = false,
        public ?string $zusatz = null,
    ) {}

    public function icon(): string
    {
        return match ($this->art) {
            self::TREFFEN => 'heroicon-o-video-camera',
            self::MEILENSTEIN => 'heroicon-o-flag',
            self::FRIST => 'heroicon-o-banknotes',
            default => 'heroicon-o-ticket',
        };
    }

    public function farbe(): string
    {
        return match ($this->art) {
            self::TREFFEN => 'primary',
            self::MEILENSTEIN => 'info',
            self::FRIST => 'warning',
            default => 'gray',
        };
    }

    public function bezeichnung(): string
    {
        return match ($this->art) {
            self::TREFFEN => 'Treffen',
            self::MEILENSTEIN => 'Meilenstein',
            self::FRIST => 'Frist',
            default => 'Ticket fällig',
        };
    }
}
