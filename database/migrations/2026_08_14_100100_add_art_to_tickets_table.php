<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Worum es geht: Fehler, Änderungswunsch, Frage oder gewöhnliche
            // Aufgabe. Getrennt von "prioritaet" (wie dringend) und "quelle"
            // (woher es kam) — ein Kunde meldet einen Fehler, und wie dringend
            // der ist, entscheidet ihr.
            $table->string('art', 20)->default('aufgabe')->after('beschreibung');

            $table->index('art');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['art']);
            $table->dropColumn('art');
        });
    }
};
