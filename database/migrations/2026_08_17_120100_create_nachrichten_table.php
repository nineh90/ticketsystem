<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die einzelnen Nachrichten einer Unterhaltung.
 *
 * Bewusst ohne "gelesen"-Kennzeichen je Nachricht: der Lesestand steht je
 * Beteiligtem an der Unterhaltung (unterhaltung_teilnehmer.gelesen_bis).
 * Andernfalls bräuchte jede Nachricht eine Zeile je Empfänger, und der
 * Empfängerkreis einer Kundenunterhaltung ändert sich mit jeder Zuordnung
 * eines Mitarbeiters — ein zum Zeitpunkt des Schreibens festgehaltener Kreis
 * wäre eine Woche später falsch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nachrichten', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unterhaltung_id')->constrained('unterhaltungen')->cascadeOnDelete();

            // Wie bei den Kommentaren: der Text überlebt den Zugang. Wird ein
            // Mitarbeiter gelöscht, bleibt der Verlauf lesbar und die Zeile
            // steht als "Gelöschter Zugang" da — ein Verlauf mit Löchern ist
            // schlimmer als einer mit einem namenlosen Absender.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('text');

            $table->timestamps();

            // Der Verlauf wird immer als Ganzes in zeitlicher Folge gelesen.
            $table->index(['unterhaltung_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nachrichten');
    }
};
