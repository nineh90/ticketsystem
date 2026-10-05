<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wer hinter einem eingelieferten Ticket steht — als Felder, nicht als Text.
 *
 * Bisher stand die Absenderadresse nur als erste Zeile in der Beschreibung.
 * Zum Lesen genügt das. Seit die Website ihre Anfragen einliefert, soll aus
 * einem solchen Ticket aber ein Kunde mit Kontakt werden können, ohne dass
 * jemand Name und Adresse aus dem Fließtext abtippt.
 *
 * Beide Spalten sind leer erlaubt und bleiben es für alles, was schon da
 * ist: bestehende Tickets werden nicht angefasst.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('absender_name')->nullable()->after('external_ref');
            $table->string('absender_email')->nullable()->after('absender_name');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['absender_name', 'absender_email']);
        });
    }
};
