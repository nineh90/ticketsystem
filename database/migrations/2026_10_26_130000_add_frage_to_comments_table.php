<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Kommentar kann eine Frage an jemanden sein (NID-36).
 *
 * Vorher waren Fragen im Team nicht von Notizen zu unterscheiden — und weil
 * interne Notizen niemanden benachrichtigten, standen Kevins Fragen an Nils
 * wochenlang an Tickets, die Nils nicht öffnete. Jetzt weiß der Kommentar,
 * an wen er sich richtet, und bis wann er offen war: beantwortet ist er,
 * sobald der Gefragte am selben Ticket etwas schreibt oder ihn von Hand
 * abhakt.
 *
 * Dazu die beiden neuen Mail-Themen für alle internen Zugänge mit eigener
 * Auswahl — dieselbe Überlegung wie bei "Meine Woche": ohne diese Zeilen
 * bekäme sie niemand, weil jeder seine Auswahl schon einmal gespeichert hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('frage_an')->nullable()->after('ist_intern')->constrained('users')->nullOnDelete();
            $table->timestamp('beantwortet_at')->nullable()->after('frage_an');

            $table->index(['frage_an', 'beantwortet_at']);
        });

        DB::table('users')
            ->where('rolle', '!=', 'kunde')
            ->whereNotNull('mail_ereignisse')
            ->get(['id', 'mail_ereignisse'])
            ->each(function (object $nutzer) {
                $auswahl = json_decode($nutzer->mail_ereignisse, true) ?? [];
                $neu = array_values(array_unique([...$auswahl, 'kommentar', 'frage']));

                DB::table('users')->where('id', $nutzer->id)->update(['mail_ereignisse' => json_encode($neu)]);
            });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frage_an');
            $table->dropColumn('beantwortet_at');
        });
    }
};
