<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Wo ein Dokument gerade steht.
 *
 * Ein Enum für Angebote und Rechnungen gemeinsam, nicht zwei. Der Grund ist
 * die Spalte: beide Stände leben in derselben, und zwei Enums darauf hießen
 * einen Cast, der von der Art des Datensatzes abhängt — genau die Sorte
 * Verzweigung, die beim Auswerten irgendwann übergangen wird. Welche Fälle
 * zu welcher Art gehören, sagt DokumentArt::staende().
 *
 * "Offen" ist bewusst für beide dasselbe Wort und nicht einmal "unbeantwortet"
 * und einmal "unbezahlt": im Alltag ist es dieselbe Frage — liegt hier noch
 * etwas herum, um das sich jemand kümmern muss.
 */
enum DokumentStand: string implements HasColor, HasLabel
{
    case Offen = 'offen';
    case Angenommen = 'angenommen';
    case Abgelehnt = 'abgelehnt';
    case Bezahlt = 'bezahlt';
    case Storniert = 'storniert';

    public function getLabel(): string
    {
        return match ($this) {
            self::Offen => 'Offen',
            self::Angenommen => 'Angenommen',
            self::Abgelehnt => 'Abgelehnt',
            self::Bezahlt => 'Bezahlt',
            self::Storniert => 'Storniert',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Offen => 'warning',
            self::Angenommen, self::Bezahlt => 'success',
            self::Abgelehnt => 'danger',
            self::Storniert => 'gray',
        };
    }

    /**
     * Zählt dieser Stand noch als offener Posten?
     *
     * Storniert ausdrücklich nicht: eine stornierte Rechnung ist erledigt,
     * auch wenn nie Geld geflossen ist. Sie in den offenen Posten zu führen,
     * wäre die Zahl, die man am Monatsende nicht erklären kann.
     */
    public function istOffen(): bool
    {
        return $this === self::Offen;
    }
}
