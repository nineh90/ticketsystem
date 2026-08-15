<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meilensteine eines Projekts — der Zeitstrahl im Kundenbereich.
 *
 * Die Alternative wäre eine Prozentzahl am Projekt gewesen. Sie ist billiger
 * zu bauen und veraltet still: nichts erinnert daran, sie zu ändern, und
 * "60 %" sagt einem Kunden ohnehin nicht, was noch fehlt. Ein Zeitstrahl aus
 * Meilensteinen sagt es, und der Prozentsatz fällt als Nebenprodukt ab —
 * erledigte durch alle, gerechnet statt getippt.
 *
 * kunden_sichtbar ist hier standardmäßig an, anders als bei den Zugangsdaten:
 * ein Meilenstein ist eine Ansage darüber, was passiert, und für die gibt es
 * keinen Grund zur Vorsicht. Der Schalter ist für die Ausnahme da — etwa
 * "Altsystem abschalten", was den Kunden nur beunruhigen würde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meilensteine', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('titel');
            $table->text('beschreibung')->nullable();

            // Datum ohne Uhrzeit: eine Zusage auf den Tag genau ist präzise
            // genug, und ein Datumsfeld übersteht die Zeitzonenrechnung, die
            // dieses System schon einmal beschäftigt hat.
            $table->date('faellig_am')->nullable();

            // Zeitstempel statt Schalter — "erledigt" ist eine Frage, "wann"
            // ist die interessantere. Sie beantwortet im Kundenbereich, ob
            // sich diese Woche etwas getan hat.
            $table->timestamp('erledigt_at')->nullable();

            $table->boolean('kunden_sichtbar')->default(true);

            // Reihenfolge von Hand: Meilensteine sind eine Erzählung, und die
            // ist selten nach Datum sortiert — ein Punkt ohne Termin gehört
            // trotzdem an eine bestimmte Stelle.
            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->timestamps();

            $table->index(['project_id', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meilensteine');
    }
};
