<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Worüber ein Zugang per Mail benachrichtigt wird.
 *
 * Bis hierher war der Mailversand alles oder nichts. Für einen einzelnen
 * Zugang ging das; sobald Kevin und später Kunden dazukommen, ist es die
 * sicherste Art, den Versand wertlos zu machen — wer täglich fünf Mails
 * bekommt, von denen ihn zwei angehen, übergeht nach einer Woche alle fünf.
 *
 * Die Fälle sind nicht erfunden, sondern die Auslöser, die es im System
 * tatsächlich gibt. Kommt einer dazu, gehört er hierhin — und dann fällt beim
 * Schreiben auf, dass jemand entscheiden muss, wer ihn bekommt.
 *
 * Die beiden letzten gehen nach außen und haben heute keine Wirkung:
 * Kundenzugänge bekommen grundsätzlich keine Mail (siehe
 * User::bekommtMailMeldungen). Sie stehen trotzdem schon da, damit die
 * Kundenstufe später eine Freigabe ist und kein Umbau.
 */
enum MailEreignis: string implements HasDescription, HasIcon, HasLabel
{
    /** Ein Kunde meldet etwas Neues. */
    case Anliegen = 'anliegen';

    /** Ein Kunde antwortet auf ein bestehendes Ticket. */
    case Antwort = 'antwort';

    /** Neue Nachricht in einem Verlauf — vom Kunden oder von intern. */
    case Nachricht = 'nachricht';

    /** Ein Kunde ändert seine Stammdaten. */
    case Stammdaten = 'stammdaten';

    /** Ein Kunde nimmt ein Angebot an oder lehnt es ab. */
    case Angebot = 'angebot';

    /** Wir haben auf ein Anliegen geantwortet — geht an den Kunden. */
    case AntwortAnKunde = 'antwort-an-kunde';

    /** Ein Ticket hat das Stadium gewechselt — geht an den Kunden. */
    case StandAnKunde = 'stand-an-kunde';

    public function getLabel(): string
    {
        return match ($this) {
            self::Anliegen => 'Neues Anliegen',
            self::Antwort => 'Antwort eines Kunden',
            self::Nachricht => 'Neue Nachricht',
            self::Stammdaten => 'Geänderte Stammdaten',
            self::Angebot => 'Antwort auf ein Angebot',
            self::AntwortAnKunde => 'Unsere Antwort an den Kunden',
            self::StandAnKunde => 'Stadienwechsel an den Kunden',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Anliegen => 'Ein Kunde meldet einen Fehler oder einen Wunsch.',
            self::Antwort => 'Ein Kunde schreibt etwas unter ein bestehendes Ticket.',
            self::Nachricht => 'Im Kundenverlauf oder in einem internen Faden.',
            self::Stammdaten => 'Anschrift, Rechnungsadresse, USt-IdNr. oder Website.',
            self::Angebot => 'Angenommen oder abgelehnt — mit Betrag.',
            self::AntwortAnKunde => 'Geht nach außen. Ohne Wirkung, solange Kundenzugänge keine Mail bekommen.',
            self::StandAnKunde => 'Geht nach außen. Ohne Wirkung, solange Kundenzugänge keine Mail bekommen.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Anliegen => 'heroicon-o-inbox-arrow-down',
            self::Antwort => 'heroicon-o-chat-bubble-left-right',
            self::Nachricht => 'heroicon-o-envelope',
            self::Stammdaten => 'heroicon-o-identification',
            self::Angebot => 'heroicon-o-document-check',
            self::AntwortAnKunde, self::StandAnKunde => 'heroicon-o-arrow-up-right',
        };
    }

    /** Kommt dieses Ereignis von außen zu uns? */
    public function nachInnen(): bool
    {
        return ! in_array($this, [self::AntwortAnKunde, self::StandAnKunde], true);
    }

    /**
     * Wie das Ereignis im Kundenbereich heißt.
     *
     * Andere Worte als getLabel(), und zwar aus demselben Grund, aus dem der
     * Kundenbereich überhaupt eigene Texte hat: "Stadienwechsel an den Kunden"
     * ist unsere Sicht auf die Sache. Er liest "wenn sich etwas an Ihrem
     * Anliegen tut".
     */
    public function getKundenLabel(): string
    {
        return match ($this) {
            self::AntwortAnKunde => 'Wenn wir Ihnen antworten',
            self::StandAnKunde => 'Wenn sich der Stand ändert',
            default => $this->getLabel(),
        };
    }

    public function getKundenDescription(): string
    {
        return match ($this) {
            self::AntwortAnKunde => 'Sobald jemand von uns etwas unter Ihr Anliegen schreibt.',
            self::StandAnKunde => 'Zum Beispiel wenn es erledigt ist oder wir etwas von Ihnen brauchen.',
            default => $this->getDescription(),
        };
    }

    /**
     * Worüber ein Kunde überhaupt benachrichtigt werden kann.
     *
     * Nur, was nach außen geht. Alles andere ist Betrieb — dass ein Kunde
     * etwas gemeldet hat, muss man ihm nicht mailen, er war es selbst.
     *
     * @return array<int, self>
     */
    public static function fuerKunden(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $fall) => ! $fall->nachInnen(),
        ));
    }

    /**
     * Die Auswahl, die ein interner Zugang standardmäßig bekommt.
     *
     * Alles, was hereinkommt — und nichts von dem, was hinausgeht. Ein
     * Mitarbeiter braucht keine Mail darüber, dass wir selbst geantwortet
     * haben; er war es meistens.
     *
     * @return array<int, string>
     */
    public static function vorgabeIntern(): array
    {
        return array_map(
            fn (self $fall) => $fall->value,
            array_filter(self::cases(), fn (self $fall) => $fall->nachInnen()),
        );
    }
}
