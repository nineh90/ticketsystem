<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Logo des Kunden.
 *
 * Anders als die Ticket-Anhänge liegt es auf der öffentlichen Platte
 * (storage/app/public, ausgeliefert unter /storage/...). Der Grund ist der
 * Verwendungszweck: das Logo erscheint als Avatar neben jedem Kommentar und
 * in Listen, also vielfach je Seite. Über eine geschützte Route wie bei den
 * Anhängen wäre jedes dieser Bilder eine eigene PHP-Anfrage — für eine
 * Grafik, die auf der Website des Kunden ohnehin frei zu sehen ist.
 *
 * Die Dateinamen vergibt Filament zufällig, die Adressen sind also nicht zu
 * erraten. Ein Screenshot aus einem Ticket bleibt geschützt, wo er hingehört;
 * das gilt hier ausdrücklich nicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('farbe');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
