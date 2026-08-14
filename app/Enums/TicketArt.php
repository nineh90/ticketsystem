<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Worum es in einem Ticket geht.
 *
 * Entstanden mit dem Kundenbereich: ein Kunde meldet nicht "ein Ticket",
 * sondern einen Fehler, einen Änderungswunsch oder eine Frage — und diese
 * drei brauchen unterschiedliche Reaktionen. Ein Fehler an einem fertigen
 * Produkt ist Gewährleistung, ein Änderungswunsch ist neue Arbeit, die
 * abgestimmt und in der Regel berechnet wird. Steht das nicht am Ticket,
 * muss jedes Mal die Beschreibung gelesen werden, um es zu unterscheiden.
 *
 * Bewusst getrennt von Prioritaet (wie dringend) und Quelle (woher es kam):
 * die Art beschreibt die Sache selbst und bleibt gleich, während die
 * Dringlichkeit sich ändert.
 */
enum TicketArt: string implements HasColor, HasIcon, HasLabel
{
    /** Etwas funktioniert nicht, das funktionieren sollte. */
    case Fehler = 'fehler';

    /** Etwas soll anders sein als vereinbart — neue Arbeit. */
    case Aenderung = 'aenderung';

    /** Keine Arbeit, sondern eine Auskunft. */
    case Frage = 'frage';

    /** Der Normalfall für intern angelegte Tickets. */
    case Aufgabe = 'aufgabe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fehler => 'Fehler',
            self::Aenderung => 'Änderungswunsch',
            self::Frage => 'Frage',
            self::Aufgabe => 'Aufgabe',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fehler => 'danger',
            self::Aenderung => 'warning',
            self::Frage => 'info',
            self::Aufgabe => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Fehler => 'heroicon-m-bug-ant',
            self::Aenderung => 'heroicon-m-pencil-square',
            self::Frage => 'heroicon-m-question-mark-circle',
            self::Aufgabe => 'heroicon-m-clipboard-document-list',
        };
    }

    /**
     * Die Auswahl, die ein Kunde bekommt.
     *
     * "Aufgabe" fehlt bewusst: sie ist die interne Sammelkategorie und sagt
     * über ein Kundenanliegen nichts aus. Wer sich nicht entscheiden kann,
     * nimmt "Frage" — daraus wird intern, was es tatsächlich ist.
     */
    public static function fuerKunden(): array
    {
        return [self::Fehler, self::Aenderung, self::Frage];
    }

    /** Was in der Kundenauswahl unter der Bezeichnung steht. */
    public function erklaerung(): string
    {
        return match ($this) {
            self::Fehler => 'Etwas funktioniert nicht so, wie es soll.',
            self::Aenderung => 'Etwas soll anders werden, als es jetzt ist.',
            self::Frage => 'Eine Frage zum Projekt oder zum Vorgehen.',
            self::Aufgabe => 'Allgemeine Aufgabe.',
        };
    }
}
