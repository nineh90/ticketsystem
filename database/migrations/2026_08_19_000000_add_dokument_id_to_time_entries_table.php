<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welche Rechnung eine Zeitbuchung abdeckt.
 *
 * Bis hierher gab es den Schalter "abrechenbar" an jeder Buchung — und
 * nichts, was ihn ausgewertet hätte. Vor jeder Rechnung stand deshalb
 * dieselbe Handarbeit: durch die Zeiten eines Kunden gehen und zusammenzählen,
 * was seit dem letzten Mal dazugekommen ist. Diese eine Spalte macht daraus
 * eine Abfrage.
 *
 * Bewusst ein Verweis auf das Dokument und kein Datum "abgerechnet_bis" am
 * Kunden. Ein Datum beantwortet die Frage "was ist noch offen" auch, aber
 * nicht die andere, die früher oder später kommt: *welche* Stunden stecken
 * eigentlich in dieser Rechnung. Mit dem Verweis lässt sich eine Rechnung
 * aufmachen und nachlesen, wofür sie steht — und eine nachgetragene Buchung
 * aus dem letzten Monat fällt nicht stillschweigend unter den Tisch, wie es
 * bei einem Stichdatum passieren würde.
 *
 * nullOnDelete: wird eine Rechnung gelöscht, sind ihre Zeiten wieder offen.
 * Das ist die richtige Richtung — sie sind dann tatsächlich nicht mehr
 * abgerechnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('dokument_id')
                ->nullable()
                ->after('ticket_id')
                ->constrained('dokumente')
                ->nullOnDelete();

            // Die eine Abfrage, die es gibt: "offen und abrechenbar".
            // Beide Spalten zusammen, weil beide Bedingungen immer gemeinsam
            // auftreten.
            $table->index(['abrechenbar', 'dokument_id']);
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['abrechenbar', 'dokument_id']);
            $table->dropConstrainedForeignId('dokument_id');
        });
    }
};
