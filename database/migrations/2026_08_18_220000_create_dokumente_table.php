<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angebote, Rechnungen und Verträge am Kunden.
 *
 * Diese Tabelle erstellt keine Rechnungen. Die PDF entsteht in sevDesk und
 * wird hier abgelegt — die Spalten daneben sind ausdrücklich nur so viel,
 * wie man braucht, um im System eine Frage zu beantworten, ohne das PDF zu
 * öffnen: was ist das, wie viel, bis wann, und ist es erledigt. Alles
 * weitere (Positionen, Steuersätze, Nummernkreise) steht im PDF und bleibt
 * dort. Eine zweite Wahrheit daneben wäre die, die als Erste veraltet.
 *
 * Die Datei liegt wie die Ticket-Anhänge auf der Platte "local", also
 * außerhalb von public/. Ein Angebot ist eine Zahl, die niemanden außer dem
 * Empfänger etwas angeht; eine erratbare Adresse unter /storage wäre bei
 * fortlaufenden Dateinamen genau das Leck, das man erst bemerkt, wenn es
 * jemand ausprobiert hat.
 *
 * kunden_sichtbar ist auch hier aus als Vorgabe, wie beim Zugangsdaten-
 * Tresor und aus demselben Grund: ein vergessener Schalter führt dazu, dass
 * der Kunde etwas nicht sieht — nie dazu, dass ein Entwurf bei ihm landet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumente', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Optional. Ein Angebot gehört oft zu genau einem Projekt und
            // erscheint dann auch dort; die Jahresrechnung für die Betreuung
            // gehört zu keinem. nullOnDelete wie beim Tresor: ein gelöschtes
            // Projekt nimmt die Rechnung nicht mit ins Grab.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // Wer hochgeladen hat. nullOnDelete, damit ein ausgeschiedener
            // Mitarbeiter nicht die Dokumente mitnimmt — anders als bei den
            // Zeitbuchungen, wo restrict richtig ist, hängt hier kein Geld
            // an der Person.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('art', 20);

            // "Relaunch Startseite", "Betreuung 2026". Das, was man in einer
            // Liste liest — nicht der Dateiname.
            $table->string('titel');

            // Die Nummer aus sevDesk: R-2026-014. Freitext, weil sie von dort
            // kommt und hier nichts vergeben wird.
            $table->string('nummer', 50)->nullable();

            $table->date('datum');

            // Eine Spalte für zwei Fragen: beim Angebot "gültig bis", bei der
            // Rechnung "zahlbar bis". Es ist derselbe Sachverhalt aus zwei
            // Richtungen (siehe DokumentArt::datumsBeschriftung). Zwei
            // Spalten, von denen je nach Art eine leer bleibt, wären beim
            // Auswerten die Fallunterscheidung, die man einmal vergisst.
            $table->date('faellig_am')->nullable();

            // numeric und nicht float: Geld in Gleitkomma zu rechnen ist der
            // Fehler, der sich erst in der Summe zeigt. 10 Stellen reichen
            // bis 99.999.999,99 — das genügt hier auf absehbare Zeit.
            $table->decimal('betrag', 10, 2)->nullable();

            $table->string('stand', 20)->nullable();

            // Wann und von wem der Kunde geantwortet hat. Beides null, solange
            // er nicht geantwortet hat — und beides bleibt stehen, wenn wir
            // den Stand danach von Hand ändern. Der Zeitstempel ist die
            // Antwort auf "hat er zugesagt oder haben wir das eingetragen".
            $table->timestamp('beantwortet_at')->nullable();
            $table->foreignId('beantwortet_von')->nullable()->constrained('users')->nullOnDelete();

            $table->string('pfad');
            $table->string('dateiname');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('groesse')->default(0);

            // Interne Notiz. Sieht der Kunde nie — dafür ist der Titel da.
            $table->text('notiz')->nullable();

            $table->boolean('kunden_sichtbar')->default(false);

            $table->timestamps();

            // Der Zugriff läuft fast immer über den Kunden, meist gefiltert
            // auf das, was er sehen darf.
            $table->index(['customer_id', 'kunden_sichtbar']);

            // Für die offenen Posten über alle Kunden hinweg.
            $table->index(['art', 'stand']);

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumente');
    }
};
