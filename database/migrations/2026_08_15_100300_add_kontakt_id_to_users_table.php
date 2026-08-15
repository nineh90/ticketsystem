<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Verbindung zwischen einem Kundenzugang und der Person dahinter.
 *
 * Optional und nullOnDelete: der Zugang bleibt bestehen, wenn der Kontakt
 * gelöscht wird. Andersherum wäre es fatal — ein gelöschter Ansprechpartner
 * würde ein funktionierendes Konto mitnehmen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('kontakt_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('kontakte')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kontakt_id');
        });
    }
};
