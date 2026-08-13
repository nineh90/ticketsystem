<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Läuft bei jedem Deploy mit (siehe deploy/deploy.sh).
 *
 * Deshalb darf hier ausschließlich stehen, was idempotent ist und produktiv
 * gebraucht wird — keine Demo- oder Testdaten. Der Seeder aus
 * fahrlehrerin_sarah setzt beim Laufen das Schema neu auf und ist genau
 * deswegen bewusst aus dem Deploy herausgehalten worden; diesen Umweg sparen
 * wir uns, indem hier gar nichts Destruktives landet.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TicketStatusSeeder::class,
        ]);
    }
}
