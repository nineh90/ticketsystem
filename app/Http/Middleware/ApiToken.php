<?php

namespace App\Http\Middleware;

use App\Enums\Quelle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zugang zur Schnittstelle über einen festen Token.
 *
 * Dieselbe Konvention wie beim Lerndex-Workflow: Authorization: Bearer <TOKEN>.
 *
 * Bewusst Token in der .env und keine Token-Tabelle mit Verwaltung im
 * Dashboard. Angefangen hat es mit genau einem Aufrufer, n8n; seit die
 * Website ihre Anfragen einliefert, sind es zwei. Zwei Zeilen in der .env
 * tragen das noch: jeder Token lässt sich einzeln widerrufen, indem man
 * seine Zeile leert oder neu belegt. Eine Tabelle mit Hashes, Ablaufdatum
 * und Oberfläche lohnt erst, wenn die Liste weiter wächst.
 *
 * Am Token hängt außerdem, woher ein Ticket kommt. Die Quelle wird hier
 * bestimmt und an die Anfrage gehängt — nicht aus einem Feld im Aufruf
 * gelesen, denn das könnte jeder Aufrufer beliebig setzen.
 *
 * Der Vergleich läuft über hash_equals: ein normales === bräuchte für jeden
 * falschen Token unterschiedlich lange und verriete damit Stück für Stück,
 * wie viele Zeichen stimmen.
 */
class ApiToken
{
    /** Unter diesem Namen hängt die Quelle an der Anfrage. */
    public const QUELLE = 'api_quelle';

    public function handle(Request $request, Closure $next): Response
    {
        // Leere Einträge fliegen heraus, bevor verglichen wird: ein nicht
        // gesetzter Website-Token darf nicht heißen, dass ein leerer
        // Bearer-Wert passt.
        $gueltig = array_filter([
            Quelle::Api->value => (string) config('ticketsystem.api_token'),
            Quelle::Website->value => (string) config('ticketsystem.api_token_website'),
        ], fn (string $token) => $token !== '');

        // Gar kein Token in der Konfiguration darf nicht bedeuten, dass
        // jeder hereinkommt — dann stünde die Schnittstelle nach einem
        // unvollständigen Deploy offen.
        if ($gueltig === []) {
            return response()->json([
                'fehler' => 'Die Schnittstelle ist nicht eingerichtet.',
            ], 503);
        }

        $gesendet = (string) $request->bearerToken();
        $quelle = null;

        // Ohne vorzeitigen Abbruch: jeder Token wird verglichen, damit die
        // Antwortzeit nicht verrät, an welchem es gelegen hat.
        foreach ($gueltig as $name => $erwartet) {
            if ($gesendet !== '' && hash_equals($erwartet, $gesendet)) {
                $quelle ??= Quelle::from($name);
            }
        }

        if ($quelle === null) {
            return response()->json([
                'fehler' => 'Kein gültiger Token.',
            ], 401);
        }

        $request->attributes->set(self::QUELLE, $quelle);

        return $next($request);
    }
}
