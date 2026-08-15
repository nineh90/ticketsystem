<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Wie weit ein Projekt ist — die Fassung, die der Kunde sieht.
 *
 * Nicht zu verwechseln mit ProjektStatus: der sagt, ob wir gerade daran
 * arbeiten (aktiv/pausiert/abgeschlossen), und ist unsere Ablage. Die Phase
 * sagt, wo das Ergebnis steht. Ein Projekt kann pausiert und trotzdem "live"
 * sein — die Seite läuft ja, wir arbeiten nur gerade nicht daran.
 *
 * Die Beschreibungen sind für den Kunden geschrieben und stehen im
 * Kundenbereich unter dem Abzeichen. Ohne sie ist "Abnahme" ein Wort aus
 * unserer Welt, bei dem niemand weiß, ob er etwas tun muss.
 */
enum ProjektPhase: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Konzept = 'konzept';
    case Umsetzung = 'umsetzung';
    case Abnahme = 'abnahme';
    case Live = 'live';
    case Betreuung = 'betreuung';

    public function getLabel(): string
    {
        return match ($this) {
            self::Konzept => 'Konzept',
            self::Umsetzung => 'In Umsetzung',
            self::Abnahme => 'Zur Abnahme',
            self::Live => 'Live',
            self::Betreuung => 'Betreuung',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Konzept => 'Wir klären, was gebaut wird.',
            // Bewusst ohne den Hinweis auf eine Vorschau: ob es eine gibt,
            // weiß die Phase nicht. Bei einem Projekt, das direkt auf der
            // eigenen Domain des Kunden entsteht, gibt es keine — der Satz
            // verwiese dann auf etwas, das nirgends steht. Was tatsächlich
            // zu sehen ist, sagt der Knopf daneben.
            self::Umsetzung => 'Wir bauen gerade daran.',
            self::Abnahme => 'Fertig zum Draufschauen — wir warten auf Ihre Rückmeldung.',
            self::Live => 'Die Seite läuft unter ihrer eigenen Adresse.',
            self::Betreuung => 'Läuft. Wir halten es aktuell und sicher.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Konzept => 'gray',
            self::Umsetzung => 'info',
            // Die einzige Phase, in der der Kunde am Zug ist — und damit die
            // einzige, die eine Farbe verdient, die auffällt.
            self::Abnahme => 'warning',
            self::Live => 'success',
            self::Betreuung => 'primary',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Konzept => 'heroicon-o-light-bulb',
            self::Umsetzung => 'heroicon-o-wrench-screwdriver',
            self::Abnahme => 'heroicon-o-eye',
            self::Live => 'heroicon-o-globe-alt',
            self::Betreuung => 'heroicon-o-shield-check',
        };
    }

    /**
     * Ab hier gibt es eine echte Adresse.
     *
     * Steuert im Kundenbereich, welcher der beiden Links der auffällige ist:
     * vorher die Vorschau, danach die laufende Seite.
     */
    public function istVeroeffentlicht(): bool
    {
        return in_array($this, [self::Live, self::Betreuung], strict: true);
    }
}
