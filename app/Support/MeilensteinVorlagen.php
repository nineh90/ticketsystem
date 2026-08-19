<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ReiseplanVorlage;
use Illuminate\Support\Collection;

/**
 * Die Reiseplan-Vorlagen, aufbereitet für das Formular hinter "Aus Vorlage".
 *
 * Sie standen bis zum 19.08.2026 in config/meilensteine.php und liegen
 * seitdem in der Datenbank (Maschinenraum → Reiseplan-Vorlagen). Der Grund
 * für den Umzug: es ist die Stelle, an der am häufigsten etwas geändert
 * wird, und jede Änderung kostete einen Deploy. Texte, die beim Kunden
 * stehen, sollen ohne Entwickler zu ändern sein.
 *
 * Diese Klasse blieb dabei, wie sie war — dieselben vier Methoden, nur eine
 * andere Quelle. Das ist der Grund, warum der Umzug an einer Stelle stattfand
 * und nicht in jedem Aufrufer.
 *
 * Der Kern ist nicht das Auslesen der Konfiguration, sondern die Frage: was
 * von der Vorlage steht bei diesem Projekt schon da? Ohne diese Antwort legt
 * der Knopf bei einem Projekt, an dem schon gearbeitet wurde, ein zweites
 * "Angebot" an — und der Kunde sieht auf seinem Zeitstrahl zwei.
 */
class MeilensteinVorlagen
{
    /** Was kurz genug ist, um zufällig in einem anderen Titel zu stecken. */
    private const MINDESTLAENGE_FUER_TEILTREFFER = 5;

    /** @return array<string, string> Schlüssel => Anzeigename, für ein Auswahlfeld. */
    public static function auswahl(): array
    {
        return ReiseplanVorlage::query()
            ->inReihenfolge()
            ->pluck('name', 'schluessel')
            ->all();
    }

    /**
     * Welche Vorlage vorausgewählt ist.
     *
     * Rückfall auf die erste, wenn keine als Vorgabe markiert ist: ein
     * Auswahlfeld ohne Vorauswahl sieht aus, als wäre nichts geladen.
     */
    public static function vorgabe(): ?string
    {
        return ReiseplanVorlage::query()->where('ist_vorgabe', true)->value('schluessel')
            ?? ReiseplanVorlage::query()->inReihenfolge()->value('schluessel');
    }

    /**
     * Die Punkte einer Vorlage, in ihrer Reihenfolge.
     *
     * @return Collection<int, array{titel: string, beschreibung: ?string}>
     */
    public static function punkte(?string $schluessel): Collection
    {
        if (blank($schluessel)) {
            return collect();
        }

        $vorlage = ReiseplanVorlage::query()
            ->where('schluessel', $schluessel)
            ->with('punkte')
            ->first();

        return collect($vorlage?->punkte ?? [])
            ->map(fn ($punkt): array => [
                'titel' => (string) $punkt->titel,
                'beschreibung' => $punkt->beschreibung,
            ])
            ->filter(fn (array $punkt): bool => filled($punkt['titel']))
            ->values();
    }

    /**
     * Steht dieser Punkt bei dem Projekt sinngemäß schon?
     *
     * Verglichen wird großzügig: Groß- und Kleinschreibung, Umlaute und alles,
     * was kein Buchstabe ist, fallen weg, und danach genügt, dass der eine
     * Titel im anderen steckt. "Erstellung eines Angebots" trifft damit auf
     * die Vorlage "Angebot", und "Unser Design Vorschlag" auf
     * "Designvorschlag" — beides real so angelegt und beides gemeint.
     *
     * Lieber einmal zu viel als zu wenig erkannt: ein fälschlich abgewählter
     * Punkt kostet einen Klick, ein übersehener Doppelgänger steht beim
     * Kunden im Zeitstrahl.
     */
    public static function stehtSchonDa(Project $projekt, string $titel): bool
    {
        return self::vorhandeneTitel($projekt)
            ->contains(fn (string $vorhanden): bool => self::aehneln($vorhanden, $titel));
    }

    /** @return Collection<int, string> */
    private static function vorhandeneTitel(Project $projekt): Collection
    {
        return $projekt->meilensteine()->pluck('titel');
    }

    private static function aehneln(string $eins, string $zwei): bool
    {
        $eins = self::normalisieren($eins);
        $zwei = self::normalisieren($zwei);

        if (blank($eins) || blank($zwei)) {
            return false;
        }

        if ($eins === $zwei) {
            return true;
        }

        $kuerzer = strlen($eins) <= strlen($zwei) ? $eins : $zwei;
        $laenger = $kuerzer === $eins ? $zwei : $eins;

        return strlen($kuerzer) >= self::MINDESTLAENGE_FUER_TEILTREFFER
            && str_contains($laenger, $kuerzer);
    }

    private static function normalisieren(string $titel): string
    {
        $titel = str($titel)->lower()->ascii()->toString();

        return (string) preg_replace('/[^a-z0-9]/', '', $titel);
    }
}
