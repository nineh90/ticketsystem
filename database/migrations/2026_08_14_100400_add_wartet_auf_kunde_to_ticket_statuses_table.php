<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert Stadien, in denen der Ball beim Kunden liegt.
 *
 * Bewusst eine Eigenschaft des Stadiums und keine Abfrage auf den Slug
 * "warten-kunde": Stadien sind konfigurierbar, sie dürfen umbenannt und
 * ergänzt werden. Ein hart verdrahteter Slug hätte beim ersten neuen Stadium
 * ("Rückfrage offen", "Freigabe ausstehend") stillschweigend aufgehört zu
 * wirken — und "stillschweigend" heißt hier: der Kunde wird nicht mehr
 * benachrichtigt und niemand merkt es.
 *
 * Dasselbe Muster wie ist_abschluss, aus demselben Grund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->boolean('wartet_auf_kunde')->default(false)->after('ist_abschluss');
        });

        // Das bestehende Stadium gleich richtig setzen. Ohne diese Zeile
        // stünde die Eigenschaft überall auf "nein" und müsste von Hand
        // nachgezogen werden — und bis dahin wartet der Kunde auf eine
        // Nachricht, die niemand ausgelöst hat.
        DB::table('ticket_statuses')
            ->where('slug', 'warten-kunde')
            ->update(['wartet_auf_kunde' => true]);
    }

    public function down(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropColumn('wartet_auf_kunde');
        });
    }
};
