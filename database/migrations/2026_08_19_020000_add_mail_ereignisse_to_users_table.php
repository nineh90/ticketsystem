<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Worüber ein Zugang per Mail benachrichtigt wird.
 *
 * Ergänzt den Schalter mail_benachrichtigungen um die Frage "worüber". Der
 * Schalter bleibt der Hauptschalter — steht er aus, ist diese Spalte ohne
 * Bedeutung.
 *
 * **null heißt: alles.** Das ist bewusst so und nicht "nichts": wer die
 * Auswahl nie angefasst hat, bekommt weiterhin jedes Ereignis — auch solche,
 * die es beim Anlegen seines Zugangs noch gar nicht gab. Eine Liste, die beim
 * Einführen einmal festgeschrieben wird, schlösse jeden künftigen
 * Ereignistyp für alle bestehenden Zugänge stillschweigend aus, und das
 * würde niemandem auffallen.
 *
 * Sobald jemand die Auswahl bearbeitet, steht hier seine Liste — dann gilt
 * sie genau so, wie er sie gesetzt hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // jsonb und nicht json — das ist auf Postgres kein Geschmack,
            // sondern Notwendigkeit: für json gibt es keinen
            // Gleichheitsoperator, und jedes "select distinct users.*" im
            // System bricht ab, sobald eine json-Spalte an der Tabelle
            // hängt. Betroffen wären sämtliche Mitarbeiter-Auswahlen. Bei
            // jsonb gibt es den Operator.
            $table->jsonb('mail_ereignisse')->nullable()->after('mail_benachrichtigungen');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mail_ereignisse');
        });
    }
};
