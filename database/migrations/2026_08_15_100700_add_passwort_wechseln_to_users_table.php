<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennzeichen: dieses Konto trägt ein Passwort, das jemand anderes vergeben
 * hat, und muss es beim nächsten Anmelden ändern.
 *
 * Der Anlass ist der fehlende Mailversand. Solange "Passwort vergessen" aus
 * ist, geht jedes Startpasswort durch einen menschlichen Kanal — vorgelesen,
 * in eine Nachricht getippt, weitergeleitet. Danach kennen es zwei Leute und
 * es steht in einem Chatverlauf. Das ist als Übergang gedacht und wird ohne
 * Nachdruck zum Dauerzustand.
 *
 * Vorgabe false: kein bestehendes Konto wird ausgesperrt oder umgeleitet.
 *
 * Bewusst kein rückwirkendes Setzen, und der Grund ist ein anderer als er
 * zunächst aussah: die beiden bestehenden Kundenzugänge haben zwar Anmeldungen
 * im Protokoll, aber die stammen sämtlich von uns selbst beim Ausprobieren.
 * Kein echter Kunde hat bisher Zugangsdaten in der Hand. Ein rückwirkendes
 * Setzen würde also niemanden schützen — es beträfe nur unsere eigenen
 * Testkonten.
 *
 * Nötig ist es auch nicht: sobald die Zugangsdaten tatsächlich herausgehen,
 * wird ohnehin ein frisches Passwort vergeben, und genau das löst die Pflicht
 * aus. Der Weg, den ein Passwort zum Kunden nimmt, ist immer derselbe — und
 * an seinem Anfang steht dieses Kennzeichen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('passwort_wechseln')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('passwort_wechseln');
        });
    }
};
