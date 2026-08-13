<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Die Wasserlinie: bis hierher hat dieser Nutzer das Dashboard zuletzt
 * gesehen.
 *
 * Beim ersten Zugriff in einer Anfrage wird der alte Stand gemerkt und
 * zurückgegeben, und erst danach auf jetzt gesetzt. Andersherum — erst
 * setzen, dann lesen — stünde die Marke immer auf "gerade eben", und es wäre
 * nie etwas neu.
 */
class DashboardBesuch
{
    private static bool $gelesen = false;

    private static ?Carbon $marke = null;

    public static function marke(?User $nutzer = null): ?Carbon
    {
        $nutzer ??= auth()->user();

        if ($nutzer === null) {
            return null;
        }

        if (self::$gelesen) {
            return self::$marke;
        }

        self::$gelesen = true;
        self::$marke = $nutzer->dashboard_gesehen_at;

        // Bewusst über den Query Builder: ein save() auf dem Model würde
        // updated_at mitziehen und damit bei jedem Aufruf des Dashboards so
        // aussehen, als wäre am Konto etwas geändert worden.
        User::query()
            ->whereKey($nutzer->getKey())
            ->update(['dashboard_gesehen_at' => now()]);

        $nutzer->setAttribute('dashboard_gesehen_at', now());
        $nutzer->syncOriginalAttribute('dashboard_gesehen_at');

        return self::$marke;
    }

    /** Nur für Tests: den Merker dieser Anfrage vergessen. */
    public static function zuruecksetzen(): void
    {
        self::$gelesen = false;
        self::$marke = null;
    }
}
