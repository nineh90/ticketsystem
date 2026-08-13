<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenzuordnung am Nutzer — für den späteren Kundenbereich.
 *
 * Bleibt in v1 bei allen Konten leer. Sie wird jetzt schon angelegt, weil das
 * Nachrüsten der Spalte zwar billig ist, die bis dahin angelegten Konten sie
 * aber nicht haben und niemand mehr rekonstruieren kann, wer zu wem gehört.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('rolle')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
