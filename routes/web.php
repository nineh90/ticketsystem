<?php

use App\Http\Controllers\AbmeldenController;
use App\Http\Controllers\AnhangController;
use App\Http\Controllers\DokumentController;
use Illuminate\Support\Facades\Route;

/*
 * Die Wurzel gehört dem Filament-Panel (siehe AdminPanelProvider: ->path('')).
 * Deshalb steht hier bewusst keine "/"-Route — sie würde sich mit dem
 * Dashboard bzw. der Weiterleitung auf /login ins Gehege kommen.
 */

/*
 * Ausliefern von Ticket-Anhängen.
 *
 * Die Dateien liegen außerhalb von public/, damit Apache sie nicht von sich
 * aus herausgibt: ein Screenshot aus einem Kundenprojekt kann Zugangsdaten
 * oder Namen zeigen. Jeder Abruf läuft deshalb durch die Anwendung und wird
 * gegen die AttachmentPolicy geprüft.
 *
 * "auth" ist Pflicht — ohne die Middleware wäre der Aufruf anonym möglich und
 * die Policy liefe ins Leere.
 */
/*
 * Abmelden aus einem der beiden Bereiche.
 *
 * Ausdrücklich AUSSERHALB der Panels: Filaments eigene Abmeldung liegt hinter
 * deren Schranke, und wer die nicht besteht, kommt auch nicht zum Abmelden
 * (siehe AbmeldenController). Ohne auth-Middleware, damit sie auch dann
 * greift, wenn die Anmeldung schon halb weg ist — abmelden, wenn man nicht
 * angemeldet ist, ist harmlos.
 */
Route::post('/abmelden/{bereich}', AbmeldenController::class)->name('abmelden');

Route::middleware(['auth'])
    ->get('/anhang/{anhang}', AnhangController::class)
    ->name('anhang.zeigen');

/*
 * Dieselbe Auslieferung, dieselbe Prüfung — nur unter /kunde.
 *
 * Der Umweg über einen zweiten Pfad hat genau einen Grund: läuft die Sitzung
 * ab, während der Kunde einen Anhang öffnet, entscheidet der Pfad, auf welche
 * der beiden Anmeldungen er geleitet wird (siehe bootstrap/app.php). Ohne ihn
 * landete er an der internen Anmeldung, die seine gültigen Zugangsdaten
 * abweist — und er hielte seinen Zugang für kaputt.
 *
 * Sicherheitsrelevant ist der Pfad nicht: welche Datei jemand bekommt,
 * entscheidet ausschließlich die AttachmentPolicy.
 *
 * "auth:kunde" und nicht bloß "auth": der Kundenbereich hat einen eigenen
 * Guard (config/auth.php). Ohne die Angabe griffe der Standard-Guard, und die
 * Policy prüfte gegen den intern angemeldeten Nutzer — was in dem Moment
 * auffällt, in dem beide Anmeldungen nebeneinander bestehen, also genau dann,
 * wenn man es am wenigsten erwartet.
 */
Route::middleware(['auth:kunde'])
    ->get('/kunde/anhang/{anhang}', AnhangController::class)
    ->name('kunde.anhang.zeigen');

/*
 * Angebote, Rechnungen, Verträge — dieselbe Bauart wie die Anhänge oben und
 * aus denselben Gründen: geschützte Route, Prüfung über die DokumentPolicy,
 * und je ein Pfad für innen und für /kunde, damit eine abgelaufene Sitzung
 * an der richtigen Anmeldung landet.
 *
 * Der Unterschied zur Anhang-Route steht in der Policy, nicht hier: beim
 * Kunden entscheidet zusätzlich die Freigabe (kunden_sichtbar). Ein Dokument,
 * das ihm gehört, aber noch nicht freigegeben ist, gibt es für ihn auch unter
 * dieser Adresse nicht.
 */
Route::middleware(['auth'])
    ->get('/dokument/{dokument}', DokumentController::class)
    ->name('dokument.zeigen');

Route::middleware(['auth:kunde'])
    ->get('/kunde/dokument/{dokument}', DokumentController::class)
    ->name('kunde.dokument.zeigen');
