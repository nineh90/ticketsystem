<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unterhaltungen — der Draht neben den Tickets.
 *
 * Bis hierher ging jedes geschriebene Wort über ein Ticket. Das ist richtig
 * für alles, was eine Aufgabe ist, und falsch für alles andere: eine
 * Rückfrage zur Rechnung, ein "wann passt Ihnen ein Termin", ein Hinweis an
 * Kevin, der kein Ticket rechtfertigt. Wer dafür ein Ticket anlegt, hat nach
 * zwei Wochen eine Ticketliste, in der die Hälfte keine Arbeit ist — und
 * genau dann fängt man an, sie zu überblättern.
 *
 * Ein laufender Faden je Gegenüber, kein Betreff, kein Status. Das ist die
 * Absicht: eine Unterhaltung wird nicht abgeschlossen, sie ruht nur. Wer
 * einen Vorgang mit Anfang und Ende braucht, hat dafür ein Ticket.
 *
 * Zwei Arten, und der Unterschied ist nicht kosmetisch:
 *
 *   kunde  — gehört einem Kunden, nicht einer Person. Wer beim Kunden
 *            mitliest, sind alle seine Zugänge; wer bei uns mitliest, sind
 *            Admins und die Zuständigen. Das ist der Grund für diese Bauart:
 *            läge der Faden zwischen zwei Personen, wäre er verwaist, sobald
 *            eine davon im Urlaub ist oder das Haus verlässt.
 *
 *   intern — zwischen zwei von uns. Hier ist der Empfängerkreis genau das
 *            Gegenteil: fest und klein, und niemand sonst liest mit, auch
 *            kein Administrator. Ein interner Draht, bei dem der Chef
 *            grundsätzlich mitliest, wird nach dem ersten Mal nicht mehr
 *            benutzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unterhaltungen', function (Blueprint $table) {
            $table->id();

            $table->string('art');

            // Nur bei art = kunde gesetzt. Eindeutig, weil es je Kunde genau
            // einen Faden gibt — zwei parallele Verläufe mit demselben Kunden
            // wären die Situation, in der die Antwort im anderen steht.
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique('customer_id');

            // Für die Sortierung der Liste, ohne je Zeile in die Nachrichten
            // zu greifen. Null heißt: angelegt, aber noch nichts geschrieben
            // — solche Unterhaltungen bleiben aus der Liste heraus, sonst
            // stünde dort jeder Kunde, der den Bereich einmal geöffnet hat.
            $table->timestamp('letzte_nachricht_am')->nullable();

            $table->timestamps();

            $table->index(['art', 'letzte_nachricht_am']);
        });

        Schema::create('unterhaltung_teilnehmer', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unterhaltung_id')->constrained('unterhaltungen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Wie weit dieser Nutzer gelesen hat. Ein Zeitstempel und keine
            // Zahl ungelesener Nachrichten: eine Zahl müsste bei jeder neuen
            // Nachricht für jeden Beteiligten fortgeschrieben werden und läuft
            // beim ersten Fehlschlag still auseinander. Der Zeitstempel wird
            // nur von dem geschrieben, der gerade liest.
            $table->timestamp('gelesen_bis')->nullable();

            $table->timestamps();

            $table->unique(['unterhaltung_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unterhaltung_teilnehmer');
        Schema::dropIfExists('unterhaltungen');
    }
};
