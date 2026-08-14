<?php

namespace Database\Seeders;

use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

/**
 * Die Ausgangs-Stadien.
 *
 * Bewusst idempotent über updateOrCreate am Slug: der Seeder darf jederzeit
 * erneut laufen, ohne Duplikate anzulegen oder eigene Anpassungen an Namen
 * und Farben zurückzusetzen — deshalb wird bei bestehenden Einträgen nichts
 * überschrieben.
 */
class TicketStatusSeeder extends Seeder
{
    public function run(): void
    {
        $stadien = [
            ['slug' => 'backlog', 'name' => 'Backlog', 'farbe' => '#6b7280', 'sortierung' => 10],
            ['slug' => 'offen', 'name' => 'Offen', 'farbe' => '#0ea5e9', 'sortierung' => 20],
            ['slug' => 'in-arbeit', 'name' => 'In Arbeit', 'farbe' => '#00bcd4', 'sortierung' => 30],
            ['slug' => 'warten-kunde', 'name' => 'Warten auf Kunde', 'farbe' => '#fbbf24', 'sortierung' => 40, 'wartet_auf_kunde' => true],
            ['slug' => 'review', 'name' => 'Review', 'farbe' => '#8b5cf6', 'sortierung' => 50],
            ['slug' => 'erledigt', 'name' => 'Erledigt', 'farbe' => '#22c55e', 'sortierung' => 60, 'ist_abschluss' => true],
            ['slug' => 'verworfen', 'name' => 'Verworfen', 'farbe' => '#ef4444', 'sortierung' => 70, 'ist_abschluss' => true],
        ];

        foreach ($stadien as $stadium) {
            // firstOrCreate statt updateOrCreate: wer ein Stadium umbenennt
            // oder umfärbt, soll das nicht beim nächsten Deploy verlieren.
            TicketStatus::firstOrCreate(
                ['slug' => $stadium['slug']],
                $stadium + ['ist_abschluss' => false, 'wartet_auf_kunde' => false],
            );
        }
    }
}
