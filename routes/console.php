<?php

use App\Console\Commands\KundeWartet;
use App\Console\Commands\Liegengebliebenes;
use App\Console\Commands\Monatsmeldung;
use App\Console\Commands\Morgenmeldung;
use App\Console\Commands\OffenePosten;
use App\Console\Commands\TreffenErinnern;
use App\Console\Commands\UhrenNachsehen;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Der Fahrplan.
 *
 * Alles hier braucht einen Dauerprozess, der "schedule:work" hält — auf dem
 * Server der Container ticketsystem-planer (deploy/docker-compose.yml und
 * docs/betrieb.md). Steht er still, passiert nichts, und zwar lautlos: keine
 * dieser Meldungen hat einen zweiten Weg.
 *
 * Zwei Sorten stehen darin, und die Uhrzeiten sind der Unterschied:
 *
 *  - **Was an einer Frist hängt** (Terminerinnerungen, wartende Kunden) läuft
 *    engmaschig, weil ein Weckruf, der zu spät kommt, keiner mehr ist.
 *  - **Was ein Bericht ist** (morgens, montags, zum Monatsanfang) läuft zu
 *    einer festen Zeit, zu der jemand liest. Berichte, die nachts um drei
 *    ankommen, stehen morgens unter zehn anderen Mails.
 *
 * withoutOverlapping überall: ein hängender Lauf soll keinen zweiten neben
 * sich starten. Gegen doppelte Meldungen steht zusätzlich in jeder Regel ein
 * Stempel oder eine Bedingung — die Sperre allein wäre zu wenig.
 */

// Erinnerungen an Treffen: 24 Stunden und eine Stunde vorher.
Schedule::command(TreffenErinnern::class)
    ->everyMinute()
    ->withoutOverlapping();

// Wartet ein Kunde zu lange auf unsere erste Antwort? Stündlich reicht: es
// geht um einen Tag, nicht um Minuten.
Schedule::command(KundeWartet::class)
    ->hourly()
    ->withoutOverlapping();

// Morgens vor dem Anfangen. Werktags — an einem Samstag ist eine Liste
// offener Tickets keine Hilfe, sondern eine Mahnung.
Schedule::command(Morgenmeldung::class)
    ->weekdays()
    ->at('07:45')
    ->withoutOverlapping();

// Abends, wenn die Uhr noch läuft. Vor dem Feierabend und nicht danach: die
// Erinnerung soll die Buchung retten, nicht sie erklären.
Schedule::command(UhrenNachsehen::class)
    ->dailyAt('18:30')
    ->withoutOverlapping();

// Montags: was in der letzten Woche liegen geblieben ist.
Schedule::command(Liegengebliebenes::class)
    ->mondays()
    ->at('08:00')
    ->withoutOverlapping();

// Montags kurz danach: wo Geld hängt.
Schedule::command(OffenePosten::class)
    ->mondays()
    ->at('08:15')
    ->withoutOverlapping();

// Zum Monatsanfang: was noch auf keiner Rechnung steht.
Schedule::command(Monatsmeldung::class)
    ->monthlyOn(1, '08:30')
    ->withoutOverlapping();
