<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->timestamp('gestartet_am');

            // Leer heißt: läuft gerade. Daran hängt die Start/Stop-Anzeige.
            $table->timestamp('beendet_am')->nullable();

            // Beim Stoppen aus der Differenz berechnet, aber eigenständig
            // gespeichert: manuelle Nachträge haben keine sinnvollen
            // Zeitstempel, und eine später korrigierte Buchung soll die
            // ursprüngliche Zeitspanne nicht verfälschen.
            $table->unsignedInteger('minuten')->default(0);

            $table->string('beschreibung')->nullable();
            $table->boolean('abrechenbar')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'gestartet_am']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
