<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Treffen mit einem Kunden — die Messe.
 *
 * Bisher lebte ein Termin ausschließlich in einer Mail und in zwei Kalendern.
 * Das reicht, solange beide Seiten die Mail wiederfinden; genau daran hakt es
 * aber jedes Mal ("wie war noch mal der Link?"). Hier steht der Termin an der
 * Stelle, an der der Kunde ohnehin nachsieht, und trägt den Link bei sich.
 *
 * Die Videokonferenz selbst bleibt draußen. `url` zeigt heute auf Google Meet
 * und morgen auf etwas anderes — das ist der ganze Sinn der Spalte: der
 * Knopf beim Kunden heißt "An Bord gehen" und führt dorthin, wo das Treffen
 * gerade stattfindet. Ein eigener Raum wäre später ein Adresswechsel, kein
 * Umbau.
 *
 * kunden_sichtbar ist wie beim Tresor und den Dokumenten mit **aus**
 * vorbelegt, anders als bei den Meilensteinen. Der Grund ist der Ablauf: ein
 * Termin entsteht beim Planen, oft als Bleistiftstrich, und erst danach wird
 * eingeladen. Ein vergessener Schalter führt so dazu, dass wir noch einmal
 * hinsehen müssen — nicht dazu, dass ein Kunde zu einem Termin erscheint,
 * den es nie gab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treffen', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Ein Treffen kann zu einem bestimmten Projekt gehören, muss aber
            // nicht: das Quartalsgespräch gehört zum Kunden, die Abnahme zum
            // Projekt. nullOnDelete und nicht cascade — verschwindet ein
            // Projekt, war das Treffen trotzdem.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();

            $table->string('titel');
            $table->text('notiz')->nullable();

            // Mit Uhrzeit, anders als bei den Meilensteinen: ein Termin ohne
            // Uhrzeit ist keiner. Die Anwendung schreibt Ortszeit (siehe die
            // Migration vom 13.08.), der Kalendereintrag rechnet beim
            // Erzeugen nach UTC um.
            $table->timestamp('beginnt_am');

            // Dauer statt Endzeitpunkt. Beim Anlegen denkt man in "eine
            // halbe Stunde", nicht in "bis 14:30" — und der Kalendereintrag
            // rechnet sich daraus in einer Zeile.
            $table->unsignedSmallInteger('dauer_minuten')->default(30);

            $table->string('url')->nullable();

            $table->boolean('kunden_sichtbar')->default(false);

            // Abgesagt statt gelöscht. Ein gelöschtes Treffen verschwindet
            // wortlos aus dem Bereich des Kunden, und er wartet trotzdem um
            // zwei Uhr davor. So bleibt es stehen und ist durchgestrichen.
            $table->timestamp('abgesagt_at')->nullable();

            $table->timestamps();

            // Die Abfrage, die es tatsächlich gibt: "das nächste Treffen
            // dieses Kunden".
            $table->index(['customer_id', 'beginnt_am']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treffen');
    }
};
