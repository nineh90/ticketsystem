<?php

use App\Http\Controllers\AnhangController;
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
Route::middleware(['auth'])
    ->get('/anhang/{anhang}', AnhangController::class)
    ->name('anhang.zeigen');
