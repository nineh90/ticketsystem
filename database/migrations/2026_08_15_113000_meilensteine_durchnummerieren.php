<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die vorhandenen Meilensteine je Projekt durchnummerieren.
 *
 * Bis hierher bekam jeder neue Meilenstein die 0 aus der Spaltenvorgabe. Wer
 * nie von Hand sortiert hat, hat damit überall dieselbe 0 stehen — und eine
 * Liste, die nach einem Feld sortiert, in dem überall dasselbe steht, ist
 * unsortiert. Postgres darf die Zeilen dann in beliebiger Folge liefern, und
 * tut es auch: derselbe Zeitstrahl sieht beim nächsten Laden anders aus.
 *
 * Neue Meilensteine hängen sich ab jetzt selbst hinten an (siehe
 * App\Models\Meilenstein). Diese Migration holt den Bestand nach.
 *
 * Als Reihenfolge wird genommen, was bisher galt: erst die Sortierung, dann
 * die id — also die Reihenfolge des Anlegens. Bei Gleichstand ist das keine
 * bessere Wahrheit, aber eine stabile, und sie entspricht dem, was beim
 * Eintragen gedacht war. Wo schon von Hand sortiert wurde, bleibt die
 * Reihenfolge unangetastet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $projekte = DB::table('meilensteine')->distinct()->pluck('project_id');

        foreach ($projekte as $projektId) {
            $ids = DB::table('meilensteine')
                ->where('project_id', $projektId)
                ->orderBy('sortierung')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $position => $id) {
                DB::table('meilensteine')
                    ->where('id', $id)
                    ->update(['sortierung' => $position + 1]);
            }
        }
    }

    /**
     * Bewusst leer: die alten Werte waren durchweg 0, und die wiederherzustellen
     * hieße, die Reihenfolge erneut zu verlieren. Ein down(), das Schaden
     * anrichtet, ist schlechter als keins.
     */
    public function down(): void {}
};
