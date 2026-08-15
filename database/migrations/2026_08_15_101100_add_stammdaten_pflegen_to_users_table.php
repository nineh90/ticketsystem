<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wer die Firmendaten seines Kunden ändern darf.
 *
 * Ein Kunde hat oft mehrere Zugänge — bei einem Verein etwa den Vorstand und
 * die Person, die die Website betreut. Beide sollen Anliegen melden können,
 * aber nicht beide die Rechnungsanschrift ändern. Bisher durfte es jeder,
 * einfach weil er einen Zugang hat.
 *
 * Vorgabe false, wie bei jedem Recht in diesem System: was nicht ausdrücklich
 * erlaubt ist, geht nicht. Die Daten bleiben trotzdem sichtbar — nur eben
 * nicht änderbar, damit ein zweiter Ansprechpartner nachsehen kann, ob die
 * Anschrift stimmt, und uns bei Bedarf Bescheid gibt.
 *
 * Bestehende Zugänge, die der einzige ihres Kunden sind, bekommen das Recht:
 * bei ihnen gibt es niemanden, dem man etwas wegnehmen könnte, und sie
 * konnten es gestern auch schon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('stammdaten_pflegen')->default(false)->after('kontakt_id');
        });

        $alleine = DB::table('users')
            ->select('customer_id')
            ->where('rolle', 'kunde')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('count(*) = 1')
            ->pluck('customer_id');

        if ($alleine->isNotEmpty()) {
            DB::table('users')
                ->where('rolle', 'kunde')
                ->whereIn('customer_id', $alleine)
                ->update(['stammdaten_pflegen' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stammdaten_pflegen');
        });
    }
};
