<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket-Stadien.
 *
 * Bewusst eine Tabelle und kein Enum: Stadien sollen sich im Dashboard
 * anlegen, umbenennen und umsortieren lassen, ohne dass dafür jemand eine
 * Migration schreibt und deployt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('farbe', 7)->default('#9ca3af');
            $table->unsignedSmallInteger('sortierung')->default(0);

            // Markiert abschließende Stadien ("Erledigt", "Verworfen").
            // Daran hängt, was als offenes Ticket zählt und wann erledigt_at
            // gesetzt wird — deshalb ein Flag und keine Namensprüfung: sonst
            // bricht das Umbenennen von "Erledigt" die halbe Auswertung.
            $table->boolean('ist_abschluss')->default(false);

            $table->timestamps();

            $table->index('sortierung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_statuses');
    }
};
