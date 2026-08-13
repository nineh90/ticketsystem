<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anhänge an Tickets — vor allem Screenshots zu Fehlerberichten.
 *
 * Eigene Tabelle statt einer JSON-Spalte am Ticket: so bleibt festgehalten,
 * wer wann was hochgeladen hat, und die Dateien lassen sich einzeln löschen,
 * ohne den ganzen Satz neu zu schreiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Pfad auf der "local"-Platte, also außerhalb von public/. Die
            // Dateien werden ausschließlich über eine geschützte Route
            // ausgeliefert — ein Screenshot aus einem Kundenprojekt kann
            // Zugangsdaten oder Namen zeigen und darf nicht für jeden
            // abrufbar sein, der die Adresse kennt.
            $table->string('pfad');

            // Der Name, den die Datei beim Hochladen hatte. Auf der Platte
            // liegt sie unter einem zufälligen Namen, damit zwei
            // "screenshot.png" sich nicht überschreiben und niemand über den
            // Dateinamen Pfade erraten kann.
            $table->string('dateiname');

            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('groesse')->default(0);

            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
