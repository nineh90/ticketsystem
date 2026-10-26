<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die offenen Spalten des Kanbans einmal nach Alter durchnummerieren
 * (NID-37).
 *
 * Bisher standen neue Tickets mit position 0 oben, und nur ein Teil der
 * Karten trug eine von Hand gezogene Position. Mit der neuen Regel —
 * Neues stellt sich hinten an — ergäbe der alte Bestand ein Durcheinander:
 * die vielen Nullen vorn, die gezogenen dahinter. Deshalb hier einmal von
 * vorn, das älteste Ticket oben. Die von Hand gezogene Reihenfolge geht
 * dabei verloren; sie war überwiegend die Reihenfolge, in der die Tickets
 * hereinkamen, nur andersherum.
 *
 * Abgeschlossene Spalten bleiben unberührt: sie sortieren nach Nummer.
 */
return new class extends Migration
{
    public function up(): void
    {
        $offene = DB::table('ticket_statuses')->where('ist_abschluss', false)->pluck('id');

        foreach ($offene as $stadium) {
            DB::table('tickets')
                ->where('ticket_status_id', $stadium)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id')
                ->each(fn (int $id, int $platz) => DB::table('tickets')->where('id', $id)->update(['position' => $platz]));
        }
    }

    public function down(): void
    {
        // Die alte Reihenfolge war keine, die man wiederherstellen wollte.
    }
};
