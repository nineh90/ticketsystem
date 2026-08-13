<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Ein Projekt ohne Kunde gibt es nicht. restrictOnDelete, weil das
            // Löschen eines Kunden mit laufenden Projekten fast immer ein
            // Versehen ist — inaktiv setzen ist der vorgesehene Weg.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('beschreibung')->nullable();

            $table->string('status', 20)->default('aktiv');
            $table->string('farbe', 7)->nullable();

            // Für den Soll-Ist-Vergleich gegen die erfassten Zeiten.
            $table->decimal('budget_stunden', 8, 2)->nullable();

            $table->timestamps();

            // Slugs müssen nur innerhalb eines Kunden eindeutig sein — zwei
            // Kunden dürfen beide ein Projekt "website" haben.
            $table->unique(['customer_id', 'slug']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
