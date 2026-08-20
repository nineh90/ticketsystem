<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei Stempel: wann an dieses Treffen erinnert wurde.
 *
 * Bis hierher meldete sich ein Termin nur, wenn sich etwas an ihm änderte —
 * angelegt, verschoben, abgesagt. Wer dabei war, hörte danach nichts mehr von
 * ihm und musste selbst daran denken. Vor allem bei den internen Terminen
 * fiel das auf: dort ist der Einzige, der dabei ist, oft derselbe, der ihn
 * angesetzt hat, und der bekommt zum Anlegen bewusst keine Meldung.
 *
 * Die Stempel stehen am Treffen und nicht in einer eigenen Tabelle. Es sind
 * zwei feste Stufen (siehe Enums\Erinnerung), und die Frage "ist das schon
 * raus?" beantwortet die Zeile, um die es geht, am besten selbst — dazu
 * verhindert genau diese Spalte, dass ein zweiter Lauf des Planers dieselbe
 * Meldung noch einmal verschickt.
 *
 * Der Index kommt dazu, weil der Planer jede Minute fragt, was ansteht. Der
 * vorhandene Index beginnt mit customer_id und hilft dieser Abfrage nicht:
 * interne Termine haben dort gar keinen Wert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treffen', function (Blueprint $table) {
            $table->timestamp('erinnert_24h_at')->nullable()->after('abgesagt_at');
            $table->timestamp('erinnert_1h_at')->nullable()->after('erinnert_24h_at');

            $table->index('beginnt_am');
        });
    }

    public function down(): void
    {
        Schema::table('treffen', function (Blueprint $table) {
            $table->dropIndex(['beginnt_am']);
            $table->dropColumn(['erinnert_24h_at', 'erinnert_1h_at']);
        });
    }
};
