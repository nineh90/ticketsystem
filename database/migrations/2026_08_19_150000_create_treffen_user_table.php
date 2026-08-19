<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wer von uns bei einem Treffen dabei ist.
 *
 * Ohne diese Tabelle kannte ein Treffen nur den Kunden: intern stand es in
 * der Akte, aber niemand von uns hatte es irgendwo stehen, und Bescheid
 * bekam auch keiner. Ein Termin, an den sich nur einer erinnert, ist die
 * Sorte, bei der um zwei Uhr einer allein im Raum sitzt.
 *
 * Als Pivot und nicht als Spalte "verantwortlich" am Treffen: bei einem
 * Quartalsgespräch sind Nils und Kevin dabei, bei einer Abnahme nur einer.
 * Eine einzelne Spalte müsste man beim zweiten Fall sofort erweitern.
 *
 * Der Kunde steht bewusst NICHT hier drin. Er ist über customer_id am
 * Treffen ohnehin dabei — er ist der Grund für den Termin. Ihn zusätzlich
 * als Teilnehmer zu führen hieße, zwei Wahrheiten darüber zu haben, wer
 * eingeladen ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treffen_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treffen_id')->constrained('treffen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Niemand zweimal an Bord. Ohne das legt ein doppelt gespeichertes
            // Formular denselben Menschen zweimal in die Liste.
            $table->unique(['treffen_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treffen_user');
    }
};
