<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunden — die oberste Ebene der Struktur Kunde → Projekt → Ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Kürzel für die Ticketnummer: LDX-42. Kurz genug, um in einer
            // Tabellenspalte oder einem Betreff nicht zu stören, und eindeutig,
            // weil die Nummer sonst nicht mehr auf einen Kunden zeigt.
            $table->string('kuerzel', 5)->unique();

            // Zähler für die Ticketnummern dieses Kunden. Bewusst hier und
            // nicht als MAX(nummer)+1 über die Tickets: der Zähler wird beim
            // Anlegen mit SELECT ... FOR UPDATE gesperrt (siehe Ticket::
            // naechsteNummer), womit zwei gleichzeitige Anfragen — etwa zwei
            // von n8n eingelieferte Mails — nicht dieselbe Nummer bekommen.
            // Ein MAX()+1 hätte genau dieses Rennen.
            $table->unsignedInteger('ticket_zaehler')->default(0);

            // Für farbige Badges in Listen und im Kanban.
            $table->string('farbe', 7)->default('#00bcd4');

            $table->string('ansprechpartner')->nullable();
            $table->string('email')->nullable();
            $table->string('telefon')->nullable();
            $table->text('notizen')->nullable();

            // Inaktive Kunden bleiben erhalten (ihre Tickets und Zeiten sollen
            // auffindbar bleiben), tauchen aber in Auswahllisten nicht mehr auf.
            $table->boolean('aktiv')->default(true);

            $table->timestamps();

            $table->index('aktiv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
