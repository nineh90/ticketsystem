<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Bewusst redundant: der Kunde steckt schon im Projekt. Er wird
            // hier trotzdem geführt, weil die Ticketnummer kundenweit läuft
            // (LDX-42) und ein UNIQUE darauf sonst nicht formulierbar wäre —
            // ein Constraint über eine Tabellengrenze hinweg kann Postgres
            // nicht. Zweiter Nutzen: "alle Tickets eines Kunden" ist ein
            // Index-Zugriff statt eines Joins.
            //
            // Die Spalte wird ausschließlich im Model gesetzt (Ticket::
            // booted), nie von Hand — sonst laufen die beiden Angaben
            // auseinander.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('nummer');

            $table->string('titel');
            $table->text('beschreibung')->nullable();

            $table->foreignId('ticket_status_id')->constrained()->restrictOnDelete();
            $table->string('prioritaet', 20)->default('normal');

            // Zuständigkeit und Urheber dürfen leer sein: unzugewiesene
            // Tickets sind ein normaler Zustand, und von n8n eingelieferte
            // Tickets haben keinen Urheber im System. nullOnDelete, damit ein
            // gelöschtes Konto nicht die Tickets mitnimmt.
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->date('faellig_am')->nullable();

            // Sortierung innerhalb einer Kanban-Spalte.
            $table->unsignedInteger('position')->default(0);

            // Herkunft: manuell | api | email. Ab v1 dabei, obwohl erst mit
            // der n8n-Anbindung genutzt — nachträglich ist die Spalte zwar
            // schnell ergänzt, aber die dann vorhandenen Tickets haben sie
            // nicht, und man weiß nie mehr, woher sie kamen.
            $table->string('quelle', 20)->default('manuell');

            // Fremdschlüssel der Quelle (z. B. Message-ID einer Mail).
            // Unique, damit ein Wiederholungslauf von n8n kein zweites Ticket
            // erzeugt.
            $table->string('external_ref')->nullable()->unique();

            $table->timestamp('erledigt_at')->nullable();
            $table->timestamps();

            // Das Netz unter der Nummernvergabe: selbst wenn die Sperre in
            // naechsteNummer() einmal umgangen wird, kann keine Nummer doppelt
            // vergeben werden — die zweite Anfrage bricht ab, statt still
            // ein Duplikat anzulegen.
            $table->unique(['customer_id', 'nummer']);

            $table->index(['ticket_status_id', 'position']);
            $table->index('assigned_to');
            $table->index('faellig_am');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
