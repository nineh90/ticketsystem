<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Tabelle hinter der Glocke im Panel.
 *
 * Laravels Standardtabelle für Datenbank-Benachrichtigungen; Filament liest
 * sie unverändert. Sie wird gebraucht, sobald Kunden selbst Anliegen melden:
 * bis dahin entstand jede Änderung im System durch jemanden, der ohnehin
 * gerade davorsaß — ein Kundenticket entsteht dagegen, während niemand
 * hinschaut, und darf nicht erst beim nächsten Blick in die Liste auffallen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');

            // json, nicht text. Laravels Standardmigration legt hier text an;
            // Filaments Glocke fragt aber nach data->>'format', und Postgres
            // kennt diesen Operator auf text nicht — die Folge war ein 500er
            // auf jeder Seite mit Glocke, mit einer Fehlermeldung, die auf
            // den Operator zeigt und nicht auf den Spaltentyp.
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Die Glocke fragt immer dasselbe: das Ungelesene dieses Nutzers,
            // neueste zuerst.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
