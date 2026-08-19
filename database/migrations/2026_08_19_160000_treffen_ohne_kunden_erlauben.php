<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Treffen dürfen ohne Kunden bestehen — Team-Besprechungen.
 *
 * Die Messe war beim ersten Wurf ausschließlich an der Kundenakte
 * aufgehängt, und damit hatte ein Termin, der nur uns betrifft, gar kein
 * Zuhause: Wochenplanung, Retro, ein Gespräch zu zweit. Genau das ist die
 * Sorte Termin, die man sonst wieder in einen fremden Kalender legt — und
 * dann steht die Hälfte der Woche woanders.
 *
 * nullable statt einer zweiten Tabelle: ein Termin ist ein Termin. Ob ein
 * Kunde dabei ist, ändert daran nichts — wohl aber, wer ihn sehen darf, und
 * das steht in Treffen::scopeSichtbarFuer. Eine eigene Tabelle für interne
 * Termine hätte jede Auswertung darüber verdoppelt (Wochenvorschau,
 * Kalender, Crew-Meldungen) und die zweite wäre irgendwann hinterher.
 *
 * **Ohne Kunde heißt niemals sichtbar für Kunden.** Der Kundenzweig in
 * sichtbarFuer filtert auf seine customer_id, und null ist keine — der Fall
 * ist damit von selbst dicht. Trotzdem steht er als Test da
 * (MesseTest::test_interne_treffen_bleiben_intern), weil "ergibt sich von
 * selbst" genau die Begründung ist, die eine spätere Änderung aushebelt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treffen', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Zurück geht es nur, wenn keine internen Treffen mehr da sind —
        // sonst bricht die Spalte an genau den Zeilen, die diese Migration
        // erst möglich gemacht hat.
        Schema::table('treffen', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};
