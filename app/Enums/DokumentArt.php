<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Was für ein Dokument am Kunden hängt.
 *
 * Die Dateien entstehen nicht hier — sie kommen als fertige PDF aus sevDesk
 * und werden hier nur abgelegt, beschrieben und freigegeben. Deshalb gibt es
 * keine Positionen, keine Steuersätze und keine Nummernkreise: alles das
 * steht schon im PDF, und eine zweite Wahrheit daneben wäre die, die
 * irgendwann nicht mehr stimmt.
 *
 * Die Art entscheidet, welche Felder überhaupt einen Sinn ergeben und welche
 * Stände es dazu gibt — siehe staende(). Ein Vertrag hat keinen
 * Zahlungsstand, ein Angebot wird nicht bezahlt.
 */
enum DokumentArt: string implements HasColor, HasIcon, HasLabel
{
    case Angebot = 'angebot';
    case Rechnung = 'rechnung';
    case Vertrag = 'vertrag';
    case Sonstiges = 'sonstiges';

    public function getLabel(): string
    {
        return match ($this) {
            self::Angebot => 'Angebot',
            self::Rechnung => 'Rechnung',
            self::Vertrag => 'Vertrag',
            self::Sonstiges => 'Sonstiges',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Angebot => 'info',
            self::Rechnung => 'warning',
            self::Vertrag => 'success',
            self::Sonstiges => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Angebot => 'heroicon-o-document-text',
            self::Rechnung => 'heroicon-o-banknotes',
            self::Vertrag => 'heroicon-o-document-check',
            self::Sonstiges => 'heroicon-o-paper-clip',
        };
    }

    /**
     * Welche Stände es zu dieser Art gibt.
     *
     * Leer heißt: diese Art hat keinen Stand. Ein Vertrag liegt einfach da.
     * Das Formular blendet das Feld dann aus, statt eine Auswahl anzubieten,
     * in der jede Antwort falsch wäre.
     *
     * @return array<int, DokumentStand>
     */
    public function staende(): array
    {
        return match ($this) {
            self::Angebot => [
                DokumentStand::Offen,
                DokumentStand::Angenommen,
                DokumentStand::Abgelehnt,
                DokumentStand::Storniert,
            ],
            self::Rechnung => [
                DokumentStand::Offen,
                DokumentStand::Bezahlt,
                DokumentStand::Storniert,
            ],
            self::Vertrag, self::Sonstiges => [],
        };
    }

    /** Trägt diese Art einen Betrag? */
    public function hatBetrag(): bool
    {
        return in_array($this, [self::Angebot, self::Rechnung], true);
    }

    /**
     * Wie das Datumsfeld "faellig_am" bei dieser Art heißt.
     *
     * Eine Spalte, zwei Bedeutungen — und das ist Absicht: es ist beide Male
     * derselbe Sachverhalt ("bis wann läuft die Uhr"), nur aus zwei
     * Richtungen. Zwei Spalten, von denen je nach Art eine leer bleibt, wären
     * beim Auswerten die Fallunterscheidung, die man einmal vergisst.
     */
    public function datumsBeschriftung(): string
    {
        return match ($this) {
            self::Angebot => 'Gültig bis',
            self::Rechnung => 'Zahlbar bis',
            self::Vertrag => 'Läuft bis',
            self::Sonstiges => 'Frist',
        };
    }

    /** Darf der Kunde auf diese Art antworten (annehmen/ablehnen)? */
    public function istEntscheidbar(): bool
    {
        return $this === self::Angebot;
    }
}
