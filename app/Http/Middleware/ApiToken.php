<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zugang zur Schnittstelle über einen festen Token.
 *
 * Dieselbe Konvention wie beim Lerndex-Workflow: Authorization: Bearer <TOKEN>.
 *
 * Bewusst ein Token in der .env und keine Token-Tabelle mit Verwaltung im
 * Dashboard: es gibt genau einen Aufrufer, n8n, und der läuft auf derselben
 * Maschine. Eine Tabelle mit Hashes, Ablaufdatum und Oberfläche wäre für
 * einen einzigen Verbraucher viel Apparat. Kämen später mehrere Integrationen
 * dazu, ist das ein isolierter Nachbau genau dieser Klasse.
 *
 * Der Vergleich läuft über hash_equals: ein normales === bräuchte für jeden
 * falschen Token unterschiedlich lange und verriete damit Stück für Stück,
 * wie viele Zeichen stimmen.
 */
class ApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $erwartet = (string) config('ticketsystem.api_token');

        // Ein leerer Token in der Konfiguration darf nicht bedeuten, dass
        // jeder hereinkommt — dann stünde die Schnittstelle nach einem
        // unvollständigen Deploy offen.
        if ($erwartet === '') {
            return response()->json([
                'fehler' => 'Die Schnittstelle ist nicht eingerichtet.',
            ], 503);
        }

        $gesendet = (string) $request->bearerToken();

        if ($gesendet === '' || ! hash_equals($erwartet, $gesendet)) {
            return response()->json([
                'fehler' => 'Kein gültiger Token.',
            ], 401);
        }

        return $next($request);
    }
}
