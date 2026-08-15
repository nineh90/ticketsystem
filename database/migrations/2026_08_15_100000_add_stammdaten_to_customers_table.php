<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aus dem Namensschild wird eine Kundenakte.
 *
 * Bis hierher wusste das System über einen Kunden vier Dinge: Name, Kürzel,
 * Farbe und eine Handvoll Kontaktfelder. Alles andere — wo die Seite liegt,
 * seit wann wir betreuen, ob überhaupt ein Vertrag läuft — stand in Köpfen
 * und Mailordnern. Diese Spalten sind die Stelle, an der es nachschlagbar
 * wird.
 *
 * Ausdrücklich additiv: jede Spalte ist nullable, keine bestehende wird
 * angefasst. Die alten Felder ansprechpartner/email/telefon bleiben, wo sie
 * sind — sie werden in die neue Tabelle "kontakte" kopiert (nicht
 * verschoben), damit nichts verloren geht, falls dort etwas schiefgeht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // --- Kaufmännisch ------------------------------------------

            // Getrennte Felder statt einer Adress-Textbox: eine Anschrift,
            // die man später auf eine Rechnung oder in einen Brief setzen
            // will, muss man sonst jedes Mal auseinandernehmen.
            $table->string('strasse')->nullable()->after('telefon');
            $table->string('plz', 10)->nullable()->after('strasse');
            $table->string('ort')->nullable()->after('plz');
            $table->string('land', 2)->default('DE')->after('ort');

            $table->string('ust_id', 20)->nullable()->after('land');

            // Eigenes Feld, weil die Rechnung fast nie an denselben Menschen
            // geht wie die Rückfrage zum Projekt — bei größeren Kunden liegt
            // dazwischen eine ganze Buchhaltung.
            $table->string('rechnung_email')->nullable()->after('ust_id');

            $table->date('kunde_seit')->nullable()->after('rechnung_email');

            // Der Lebenszyklus einer Kundenbeziehung, den "aktiv" allein
            // nicht abbildet: ein Interessent ist kein ruhender Kunde, und
            // beide sind nicht dasselbe wie beendet. "aktiv" bleibt daneben
            // bestehen und bedeutet weiterhin nur "taucht in Auswahllisten
            // auf" — der Schalter, nicht der Zustand.
            $table->string('betreuung', 20)->default('aktiv')->after('kunde_seit');

            // --- Technisch ---------------------------------------------

            $table->string('website')->nullable()->after('betreuung');

            // Wo das meiste dieses Kunden liegt. Die einzelnen Adressen
            // stehen am Projekt (live_url) — hier steht, bei wem man anruft,
            // wenn der Server nicht antwortet.
            $table->string('hoster')->nullable()->after('website');

            // Bewusst Freitext und keine Auswahlliste: was ihr anbietet,
            // ändert sich schneller als ein Enum, und ein Kunde mit einer
            // Sonderabsprache passt in keine Liste. Die Auswertung "wer hat
            // eigentlich Wartung?" ist eine Suche, kein Bericht.
            $table->string('vertragsart')->nullable()->after('hoster');

            $table->date('vertrag_bis')->nullable()->after('vertragsart');
            $table->unsignedSmallInteger('kuendigungsfrist_tage')->nullable()->after('vertrag_bis');

            // Für "welche Verträge laufen demnächst aus".
            $table->index('vertrag_bis');
            $table->index('betreuung');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['vertrag_bis']);
            $table->dropIndex(['betreuung']);

            $table->dropColumn([
                'strasse', 'plz', 'ort', 'land', 'ust_id', 'rechnung_email',
                'kunde_seit', 'betreuung', 'website', 'hoster', 'vertragsart',
                'vertrag_bis', 'kuendigungsfrist_tage',
            ]);
        });
    }
};
