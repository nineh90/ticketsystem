<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rolle und Freigabe am Nutzer.
 *
 * panel_zugang ist absichtlich vom Konto getrennt: sobald später Kunden eigene
 * Zugänge bekommen, sitzen sie in derselben users-Tabelle wie das Team. Ohne
 * dieses Flag käme jeder neu angelegte Account sofort ins interne Panel — mit
 * Blick auf alle Kunden und alle Stundensätze. Der Default ist deshalb false;
 * freigegeben wird ausdrücklich.
 *
 * (Dasselbe Muster wie in kein-einzelfall, dort mit derselben Begründung für
 * Vereinsmitglieder gegenüber der Redaktion.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rolle', 20)->default('mitarbeiter')->after('email');
            $table->boolean('panel_zugang')->default(false)->after('rolle');

            // Getrennt von panel_zugang: ein ausgeschiedener Mitarbeiter wird
            // deaktiviert, nicht gelöscht — seine Tickets, Kommentare und
            // Zeitbuchungen sollen zuordenbar bleiben.
            $table->boolean('aktiv')->default(true)->after('panel_zugang');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rolle', 'panel_zugang', 'aktiv']);
        });
    }
};
