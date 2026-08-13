<?php

use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\ApiToken;
use Illuminate\Support\Facades\Route;

/*
 * Schnittstelle für n8n.
 *
 * Alle Routen hinter dem Token aus TICKET_API_TOKEN. Zusätzlich ein
 * Drosselwert: 60 Aufrufe je Minute reichen für einen Mail-Abgleich mit
 * Abstand und begrenzen den Schaden, falls ein Workflow in eine Schleife
 * gerät — was bei n8n schneller passiert, als einem lieb ist.
 */
Route::middleware([ApiToken::class, 'throttle:60,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('/projects', [TicketController::class, 'projekte']);
        Route::post('/tickets', [TicketController::class, 'anlegen']);
    });
