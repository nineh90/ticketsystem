<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wer Meldungen zusätzlich per Mail bekommt.
 *
 * Ein Schalter je Zugang und keine globale Einstellung — das ist der
 * eigentliche Zweck: der Versand wird stufenweise eingeführt. Erst bekommt
 * ihn ein einziger Zugang (Nils), dann Kevin, und erst viel später ein Kunde.
 * Eine Einstellung "Mailversand an/aus" für das ganze System hätte diese
 * Stufen nicht.
 *
 * Vorgabe aus. Wer nichts einstellt, bekommt keine Mail — dieselbe Richtung
 * wie beim Zugangsdaten-Tresor und bei den Dokumenten: ein vergessener
 * Schalter führt dazu, dass etwas *nicht* passiert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mail_benachrichtigungen')->default(false)->after('panel_zugang');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mail_benachrichtigungen');
        });
    }
};
