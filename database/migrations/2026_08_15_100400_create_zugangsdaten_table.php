<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Tresor: Zugangsdaten zu einem Kunden oder einem seiner Projekte.
 *
 * Ein Tresor für beide Seiten, mit einem Schalter je Eintrag. "So melden Sie
 * sich in Ihrer Demo an" gehört in den Kundenbereich; unser SFTP-Zugang zum
 * selben Server gehört dort niemals hin. Beides an einem Ort zu pflegen ist
 * nicht Bequemlichkeit, sondern der Punkt: wer ein Passwort einträgt, sieht
 * im selben Formular, wer es zu sehen bekommt. Zwei getrennte Listen hießen,
 * dass man die Frage beim Anlegen gar nicht gestellt bekommt.
 *
 * Der Standard ist deshalb "nicht sichtbar". Ein vergessener Schalter führt
 * dazu, dass der Kunde etwas nicht sieht — nie dazu, dass er zu viel sieht.
 *
 * Das Passwort liegt verschlüsselt (Model-Cast "encrypted", also über den
 * APP_KEY). Zwei Folgen, die man wissen muss: ein Wechsel des APP_KEY macht
 * alle Einträge unlesbar, und es lässt sich nicht danach suchen oder
 * sortieren. Beides ist hier richtig — ein Klartext-Passwortfeld in einer
 * Datenbank, von der es nächtliche Abzüge gibt, ist es nicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zugangsdaten', function (Blueprint $table) {
            $table->id();

            // Immer am Kunden, auch wenn ein Projekt gesetzt ist. Der Kunde
            // ist die Ebene, auf der die Sichtbarkeit entschieden wird — ein
            // Eintrag ohne ihn wäre einer, für den niemand zuständig ist.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Optional: Zugänge zur Demo eines bestimmten Projekts hängen am
            // Projekt und erscheinen dort. nullOnDelete, damit ein gelöschtes
            // Projekt seine Zugangsdaten nicht mitnimmt — sie werden dann zu
            // allgemeinen Zugangsdaten des Kunden und fallen beim nächsten
            // Blick auf, statt still zu verschwinden.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // "WordPress-Admin", "Basic-Auth der Vorschau", "Strato SFTP".
            $table->string('bezeichnung');

            $table->string('url')->nullable();
            $table->string('benutzername')->nullable();

            // text und nicht string: der Geheimtext ist ein Vielfaches länger
            // als das Passwort und stößt bei varchar(255) an die Grenze.
            $table->text('passwort')->nullable();

            // Was man sonst noch wissen muss: "Zwei-Faktor läuft über die
            // Handynummer von Ali", "nur aus dem Büro-Netz erreichbar".
            $table->text('hinweis')->nullable();

            $table->boolean('kunden_sichtbar')->default(false);

            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->timestamps();

            $table->index(['customer_id', 'kunden_sichtbar']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zugangsdaten');
    }
};
