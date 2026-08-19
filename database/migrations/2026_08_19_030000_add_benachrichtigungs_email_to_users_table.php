<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Adresse, an der ein Kunde benachrichtigt werden will — und der Beleg,
 * dass sie funktioniert.
 *
 * Eine eigene Spalte und nicht users.email. Die Anmeldeadresse haben wir
 * beim Anlegen eingetippt; sie ist manchmal geraten, manchmal ein geteiltes
 * Postfach, und niemand hat je geprüft, ob jemand sie liest. Sie zu benutzen
 * hieße, Ticketinhalte an eine Adresse zu schicken, von der wir nur
 * annehmen, dass sie stimmt.
 *
 * Der Kunde nennt deshalb selbst eine Adresse und bestätigt sie über einen
 * Link. Erst der Zeitstempel in bestaetigt_at macht Mail an ihn möglich —
 * geprüft in User::bekommtMailMeldungen(). Ändert er die Adresse, fällt der
 * Zeitstempel weg und der Weg beginnt von vorn.
 *
 * gefragt_at hält fest, dass er die Frage überhaupt gesehen hat. Ohne das
 * ließe sich "will nicht" nicht von "hat noch nie hingeschaut" unterscheiden,
 * und der Hinweis in seinem Bereich stünde für immer da.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('benachrichtigungs_email')->nullable()->after('mail_ereignisse');
            $table->timestamp('benachrichtigungs_email_bestaetigt_at')->nullable()->after('benachrichtigungs_email');
            $table->timestamp('benachrichtigungen_gefragt_at')->nullable()->after('benachrichtigungs_email_bestaetigt_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'benachrichtigungs_email',
                'benachrichtigungs_email_bestaetigt_at',
                'benachrichtigungen_gefragt_at',
            ]);
        });
    }
};
