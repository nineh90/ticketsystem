<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Ticket, das aus einem angenommenen Angebot entstanden ist.
 *
 * Sagt Ja der Kunde, fängt bei uns die Arbeit an — und bis hierher fing sie
 * damit an, dass jemand von Hand ein Ticket anlegte und den Betreff aus dem
 * Angebot abtippte. Das übernimmt jetzt Support\Automatik.
 *
 * Die Spalte ist dabei mehr als eine Verknüpfung: sie ist die Sperre, die
 * verhindert, dass zweimal dasselbe entsteht. Wer den Stand versehentlich
 * auf "offen" und zurück auf "angenommen" setzt, bekommt kein zweites
 * Ticket — hier steht ja schon eines.
 *
 * nullOnDelete: wird das Ticket gelöscht, bleibt das Angebot. Es hat dann
 * wieder kein Folgeticket, und ein erneutes Annehmen legt eines an. Das ist
 * die richtige Richtung — das Angebot ist der Vorgang, das Ticket die Arbeit
 * daran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumente', function (Blueprint $table) {
            $table->foreignId('folgeticket_id')->nullable()->after('beantwortet_von')
                ->constrained('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dokumente', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folgeticket_id');
        });
    }
};
