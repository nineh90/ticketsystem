<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wann wir uns selbst daran erinnert haben, dass ein Kunde wartet.
 *
 * Der Planer sieht stündlich nach, ob ein gemeldetes Anliegen länger als
 * einen Tag ohne Antwort von uns dasteht (Support\Wache::kundeWartet). Ohne
 * diesen Stempel meldete er dasselbe Ticket jede Stunde erneut — und eine
 * Meldung, die stündlich wiederkommt, schaltet man ab, nicht ab er.
 *
 * Ein Stempel und kein Zähler: es geht um das eine Mal, an dem jemand darauf
 * gestoßen wird. Wer danach weiter nichts tut, sieht das Ticket ohnehin im
 * Wochenbericht über Liegengebliebenes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('nachgehakt_at')->nullable()->after('erledigt_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('nachgehakt_at');
        });
    }
};
