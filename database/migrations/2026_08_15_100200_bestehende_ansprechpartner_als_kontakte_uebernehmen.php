<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die bisherigen Ansprechpartner in die neue Kontakte-Tabelle übernehmen.
 *
 * Kopiert, nicht verschoben: customers.ansprechpartner/email/telefon bleiben
 * unverändert stehen. Das ist die Absicherung — geht bei der Übernahme etwas
 * daneben oder gefällt das Ergebnis nicht, liegt das Original noch da und
 * diese Migration lässt sich gefahrlos zurückrollen. Die alten Spalten
 * verschwinden erst aus dem Formular, nicht aus der Datenbank.
 *
 * Angelegt wird nur, wo tatsächlich etwas steht, und nur, wo der Kunde noch
 * gar keine Kontakte hat. Damit ist ein zweiter Lauf folgenlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kunden = DB::table('customers')
            ->select('id', 'ansprechpartner', 'email', 'telefon')
            ->get();

        foreach ($kunden as $kunde) {
            // Ein Kunde, bei dem alle drei Felder leer sind, bekäme einen
            // Kontakt ohne jede Information — eine Zeile, die man nur wieder
            // löschen muss.
            if (blank($kunde->ansprechpartner) && blank($kunde->email) && blank($kunde->telefon)) {
                continue;
            }

            if (DB::table('kontakte')->where('customer_id', $kunde->id)->exists()) {
                continue;
            }

            DB::table('kontakte')->insert([
                'customer_id' => $kunde->id,
                // Steht nur eine Mailadresse da, ist die immerhin ein Name,
                // an dem man die Zeile wiedererkennt. "Ansprechpartner" als
                // Platzhalter wäre in einer Liste von fünf Kunden nutzlos.
                'name' => $kunde->ansprechpartner ?: ($kunde->email ?: 'Ansprechpartner'),
                'email' => $kunde->email,
                'telefon' => $kunde->telefon,
                'hauptkontakt' => true,
                'aktiv' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Zurück heißt: nur die hier angelegten Zeilen wieder weg. Erkennbar
     * daran, dass sie Hauptkontakt sind und keine Funktion tragen — von Hand
     * gepflegte Kontakte haben eine, und wer eine nachträgt, schützt seinen
     * Eintrag damit. Mehr Sicherheit gäbe nur eine Herkunftsspalte, und die
     * bliebe für immer in der Tabelle stehen, um eine Frage zu beantworten,
     * die sich genau einmal stellt.
     */
    public function down(): void
    {
        DB::table('kontakte')
            ->where('hauptkontakt', true)
            ->whereNull('funktion')
            ->whereNull('notiz')
            ->delete();
    }
};
