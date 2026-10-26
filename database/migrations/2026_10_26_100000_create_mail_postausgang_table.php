<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mails, die nicht sofort hinausgehen, sondern gesammelt (NID-35).
 *
 * Eine Zeile je Empfänger und Meldung. Der Planer liest sie zu festen Zeiten
 * und macht daraus eine einzige Mail je Empfänger — danach sind die Zeilen
 * weg. Die Tabelle ist also kein Archiv, sondern ein Briefkasten, der
 * zweimal am Tag geleert wird.
 *
 * Titel, Text und Knopf stehen fertig darin, so wie sie an der Glocke
 * standen. Die Prüfungen dagegen (Schalter, Themenwahl, Sichtbarkeit beim
 * Kunden) werden beim Leeren noch einmal gemacht: zwischen Meldung und
 * Versand liegen bis zu vierundzwanzig Stunden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_postausgang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ereignis');
            $table->string('herkunft')->nullable();
            // Die Glockenmeldung, die zu dieser Zeile gehört. Ist sie beim
            // Leeren schon gelesen, braucht es keine Mail mehr dazu.
            $table->uuid('notification_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titel');
            $table->text('text')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('farbe')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_postausgang');
    }
};
