<?php

namespace App\Support;

use App\Models\User;

/**
 * Erklärt leere Listen.
 *
 * Ein Mitarbeiter ohne Projektzuordnung sieht überall nichts — das ist so
 * gewollt, aber ohne Erklärung sieht es aus wie ein kaputtes System. Die
 * Standardtexte ("keine Tickets vorhanden, oder die Filter sind zu eng")
 * schicken ihn dann auf die Suche nach einem Filter, der gar nicht das
 * Problem ist.
 *
 * Diese Klasse liefert an einer Stelle den passenden Text, damit er in allen
 * Listen, Widgets und auf dem Kanban gleich lautet.
 */
class Sichtbarkeit
{
    /** Betrifft es diesen Nutzer? Admins nie — sie sehen ohnehin alles. */
    public static function ohneProjekte(?User $nutzer = null): bool
    {
        $nutzer ??= auth()->user();

        if ($nutzer === null || $nutzer->istAdmin()) {
            return false;
        }

        return $nutzer->projects()->doesntExist();
    }

    /**
     * Erklärung für den Leerzustand, oder null wenn die Liste aus einem
     * anderen Grund leer ist.
     */
    public static function hinweis(): ?string
    {
        if (! self::ohneProjekte()) {
            return null;
        }

        return 'Dir ist noch kein Projekt zugeordnet — deshalb ist hier nichts zu sehen. '
            .'Ein Administrator ordnet dich unter Verwaltung → Nutzer einem Projekt zu.';
    }

    /** Überschrift für den Leerzustand. */
    public static function ueberschrift(string $standard): string
    {
        return self::ohneProjekte() ? 'Kein Projekt zugeordnet' : $standard;
    }

    /** Beschreibung für den Leerzustand. */
    public static function beschreibung(string $standard): string
    {
        return self::hinweis() ?? $standard;
    }
}
