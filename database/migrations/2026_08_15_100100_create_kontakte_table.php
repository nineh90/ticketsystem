<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Menschen beim Kunden.
 *
 * Eigene Tabelle und nicht die Nutzertabelle: ein Ansprechpartner ist nicht
 * dasselbe wie ein Zugang. Der Buchhalter, an den die Rechnung geht, und der
 * Techniker beim Hoster kommen nie ins Panel — trotzdem braucht man ihre
 * Nummer, und zwar an derselben Stelle wie die der anderen. Umgekehrt würde
 * ein Konto für jeden von ihnen bedeuten, Zugänge anzulegen, die niemand
 * benutzt und die trotzdem auf ein Passwort warten.
 *
 * Ein Kundenzugang darf auf einen Kontakt zeigen (users.kontakt_id), muss es
 * aber nicht. So steht eine Person einmal da statt zweimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontakte', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete, anders als bei den Projekten: ein Kontakt ohne
            // Kunden ist keine Information, sondern eine verwaiste Zeile. Zum
            // Löschen kommt es ohnehin selten — Kunden werden inaktiv gesetzt.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // "Vorstand", "Buchhaltung", "betreut die Website" — der Grund,
            // warum man diesen und keinen anderen anruft.
            $table->string('funktion')->nullable();

            $table->string('email')->nullable();
            $table->string('telefon')->nullable();
            $table->text('notiz')->nullable();

            // Wer gemeint ist, wenn nur einer gemeint sein kann: der Name auf
            // der Übersicht, der Empfänger einer Rückfrage. Es kann mehrere
            // geben; erzwungen wird die Eindeutigkeit nicht, weil eine
            // Datenbank, die beim Speichern des zweiten Hauptkontakts einen
            // Fehler wirft, in der Praxis nur nervt.
            $table->boolean('hauptkontakt')->default(false);

            // Ausgeschiedene Ansprechpartner deaktivieren statt löschen — was
            // mit ihnen besprochen wurde, bleibt sonst ohne Namen.
            $table->boolean('aktiv')->default(true);

            $table->timestamps();

            $table->index(['customer_id', 'aktiv']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontakte');
    }
};
