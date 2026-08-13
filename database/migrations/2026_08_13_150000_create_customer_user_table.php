<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung ganzer Kunden an Mitarbeiter.
 *
 * Zweiter Weg neben project_user, nicht dessen Ersatz: Wer einem Kunden
 * zugeordnet ist, sieht alle dessen Projekte — auch die, die es heute noch
 * nicht gibt. Genau das ist der Unterschied zur Projektzuordnung, bei der man
 * bei jedem neuen Projekt daran denken muss.
 *
 * Beide Wege gelten nebeneinander. Sichtbar ist ein Projekt, wenn der Nutzer
 * ihm direkt zugeordnet ist ODER seinem Kunden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user');
    }
};
