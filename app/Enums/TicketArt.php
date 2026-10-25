<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Worum es in einem Ticket geht.
 *
 * Entstanden mit dem Kundenbereich: Fehler, Änderungswunsch und Frage
 * brauchen unterschiedliche Reaktionen. Ein Fehler an einem fertigen
 * Produkt ist Gewährleistung, ein Änderungswunsch ist neue Arbeit, die
 * abgestimmt und in der Regel berechnet wird. Steht das nicht am Ticket,
 * muss jedes Mal die Beschreibung gelesen werden, um es zu unterscheiden.
 *
 * Eingeordnet wird intern. Bis NID-33 hat der Kunde selbst gewählt; seitdem
 * meldet er nur noch "ein Anliegen", und wir entscheiden, was es ist.
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

    /** Der Normalfall für intern angelegte Tickets — und für Kundenanliegen, bis wir sie einordnen. */
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
}
