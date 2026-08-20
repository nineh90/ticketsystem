<?php

use App\Console\Commands\TreffenErinnern;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Erinnerungen an bevorstehende Treffen.
 *
 * Jede Minute, und das ist nicht zu oft: es sind zwei Abfragen über einen
 * Index, und der Zweck der kurzen Stufe ist Pünktlichkeit — eine Erinnerung
 * "eine Stunde vorher", die alle fünf Minuten nachsieht, kommt im
 * ungünstigsten Fall fünf Minuten zu spät.
 *
 * withoutOverlapping, weil ein hängender Lauf sonst einen zweiten neben sich
 * startet. Doppelte Meldungen verhindert zwar schon Messe::faellige(), aber
 * zwei Prozesse, die sich gegenseitig die Zeilen wegschnappen, sind trotzdem
 * nichts, was man beim Suchen eines Fehlers noch daneben haben will.
 *
 * Damit hier überhaupt etwas läuft, muss ein Dauerprozess "schedule:work"
 * halten — auf dem Server der Container ticketsystem-planer, siehe
 * deploy/docker-compose.yml und docs/betrieb.md.
 */
Schedule::command(TreffenErinnern::class)
    ->everyMinute()
    ->withoutOverlapping();
