<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei Adressen statt einer, und der Produktionsstand als eigenes Feld.
 *
 * Bis hierher gab es nur demo_url, und die musste beides sein: die Vorschau
 * während der Arbeit und die fertige Seite danach. Das geht so lange gut, bis
 * beide gleichzeitig existieren — und genau dann, kurz vor dem Umschalten,
 * braucht der Kunde die Unterscheidung am dringendsten. Ab jetzt ist
 * demo_url die Vorschau und live_url die echte Adresse.
 *
 * "phase" ist der Stand, den der Kunde sieht, und nicht zu verwechseln mit
 * "status" daneben: status ist unsere Ablage (aktiv/pausiert/abgeschlossen),
 * phase ist die Antwort auf "wie weit seid ihr?". Ein Projekt kann intern
 * pausiert und für den Kunden trotzdem in der Abnahme stehen.
 *
 * Der Vorgabewert "umsetzung" ist gewählt, weil er auf die elf bestehenden
 * Projekte zutrifft. Er ändert keine Zeile inhaltlich — es gab vorher nichts
 * anderes an dieser Stelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('live_url')->nullable()->after('demo_url');
            $table->string('phase', 20)->default('umsetzung')->after('status');

            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['phase']);
            $table->dropColumn(['live_url', 'phase']);
        });
    }
};
