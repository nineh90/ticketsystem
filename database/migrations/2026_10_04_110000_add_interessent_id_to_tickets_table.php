<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welcher Kunde aus einer Anfrage geworden ist.
 *
 * Ein Anfrage-Ticket bleibt, wo es entstanden ist: beim Kunden "Eingang",
 * mit seiner Kennung ANF-… und seiner Adresse. Beides hat die Website an der
 * Anfrage gespeichert; wanderte das Ticket zum neuen Kunden, bekäme es dort
 * eine neue Nummer und der gespeicherte Link zeigte ins Leere.
 *
 * Also ein Verweis statt eines Umzugs. customer_id sagt weiter, wem die
 * Nummer gehört, diese Spalte sagt, wer daraus geworden ist.
 *
 * nullOnDelete: verschwindet der Interessent wieder, ist das Ticket schlicht
 * wieder eine Anfrage, aus der noch nichts geworden ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('interessent_id')
                ->nullable()
                ->after('absender_email')
                ->constrained('customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interessent_id');
        });
    }
};
