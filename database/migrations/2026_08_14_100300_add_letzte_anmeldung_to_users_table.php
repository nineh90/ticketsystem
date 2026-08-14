<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vor allem für die Kundenzugänge: nachdem man ein Startpasswort
            // weitergegeben hat, ist die erste Frage immer "hat er sich
            // überhaupt schon angemeldet?". Ohne diese Spalte lässt sich das
            // nicht beantworten, und man schickt zum dritten Mal dieselbe
            // Nachricht hinterher.
            $table->timestamp('letzte_anmeldung_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('letzte_anmeldung_at');
        });
    }
};
