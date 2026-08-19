<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reiseplan-Vorlagen als Tabellen statt als Konfigurationsdatei.
 *
 * Bisher standen sie in config/meilensteine.php. Das war richtig, solange
 * niemand daran wollte — inzwischen ist es die Stelle, an der am häufigsten
 * etwas geändert wird, und jede Änderung kostete einen Deploy. Texte, die
 * beim Kunden stehen, sollen ohne mich zu ändern sein.
 *
 * Zwei Tabellen und keine JSON-Spalte: die Punkte werden sortiert, einzeln
 * bearbeitet und einzeln umgehängt. Dazu die Vorgeschichte mit jsonb — für
 * `json` kennt Postgres keinen Gleichheitsoperator, und ein `select distinct`
 * über eine Tabelle mit json-Spalte bricht ab (siehe die Migration für
 * users.mail_ereignisse). Eine eigene Tabelle hat das Problem nie.
 *
 * `reiseplan_` im Namen und nicht bloß `vorlagen`: es wird weitere Vorlagen
 * geben (Checklisten je Kunde stehen auf der Wunschliste), und eine Tabelle,
 * die "vorlagen" heißt, ist die, um die man dann herumbauen muss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reiseplan_vorlagen', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Der Schlüssel, unter dem die Vorlage im Auswahlfeld steht. Er
            // bleibt bestehen, wenn der Name sich ändert — sonst zeigte ein
            // gespeicherter Formularzustand ins Leere.
            $table->string('schluessel')->unique();

            $table->unsignedSmallInteger('sortierung')->default(0);

            // Welche im Formular vorausgewählt ist. Als Schalter und nicht
            // als Eintrag in einer Einstellungstabelle: die Frage gehört an
            // die Vorlage, und beim Umschalten sieht man sofort, wo sie
            // vorher stand.
            $table->boolean('ist_vorgabe')->default(false);

            $table->timestamps();
        });

        Schema::create('reiseplan_punkte', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reiseplan_vorlage_id')
                ->constrained('reiseplan_vorlagen')
                ->cascadeOnDelete();

            $table->string('titel');
            $table->text('beschreibung')->nullable();

            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->timestamps();

            $table->index(['reiseplan_vorlage_id', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reiseplan_punkte');
        Schema::dropIfExists('reiseplan_vorlagen');
    }
};
