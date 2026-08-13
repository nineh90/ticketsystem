<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sicherheits-Header.
 *
 * Angelehnt an app/Http/Middleware/SicherheitsHeader.php aus kein-einzelfall,
 * mit einer bewussten Abweichung bei der Content-Security-Policy.
 *
 * Dort steht script-src ohne 'unsafe-eval', und der Kommentar beschreibt, was
 * das kostet: Alpine wertet den Inhalt von HTML-Attributen zur Laufzeit über
 * new Function() aus und hört ohne 'unsafe-eval' einfach auf zu arbeiten — der
 * Browser meldet es nur in der Entwicklerkonsole, für den Benutzer reagieren
 * die Knöpfe stumm nicht mehr. kein-einzelfall kann sich das leisten, weil es
 * absichtlich ohne JS-Framework gebaut ist.
 *
 * Filament steht auf Livewire und Alpine. Dieselbe Policy würde hier nicht
 * einzelne Knöpfe, sondern die gesamte Oberfläche unbenutzbar machen. Also:
 * 'unsafe-eval' bleibt drin, und das ist eine echte Abschwächung — kein
 * Versehen. Vertretbar ist sie, weil hinter dem Login nur eigene Mitarbeiter
 * sitzen und keine fremden Inhalte gerendert werden.
 *
 * Was dadurch NICHT wegfällt: frame-ancestors, base-uri, object-src,
 * form-action und die vier Header außerhalb der CSP wirken unverändert.
 */
class SicherheitsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Downloads, JSON-Antworten und Dateien nicht anfassen.
        if (! $this->istHtml($response)) {
            return $response;
        }

        foreach ($this->header($request) as $name => $wert) {
            $response->headers->set($name, $wert);
        }

        return $response;
    }

    private function header(Request $request): array
    {
        $header = [
            // Kein Erraten von Dateitypen — verhindert, dass ein Upload als
            // Skript ausgeführt wird. Relevant, sobald Ticket-Anhänge kommen.
            'X-Content-Type-Options' => 'nosniff',

            // Nicht in fremde Rahmen einbettbar (Clickjacking).
            'X-Frame-Options' => 'DENY',

            // Beim Verlassen der Seite die Herkunft nicht mitschicken. Hier
            // besonders sinnvoll, weil URLs Kunden- und Projektnamen enthalten.
            'Referrer-Policy' => 'no-referrer',

            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), '
                .'payment=(), usb=(), interest-cohort=()',

            'Content-Security-Policy' => $this->csp(),
        ];

        // HSTS nur über HTTPS. Über HTTP wäre er wirkungslos, lokal würde er
        // die Entwicklung lahmlegen (der Browser merkt sich die Regel).
        if ($request->secure()) {
            $header['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $header;
    }

    private function csp(): string
    {
        $regeln = [
            "default-src 'self'",

            // 'unsafe-eval' und 'unsafe-inline': von Livewire/Alpine gefordert,
            // siehe Klassenkommentar. Ein Nonce hilft hier nicht — Alpine
            // braucht die Auswertung selbst, nicht nur erlaubte Skript-Tags.
            "script-src 'self' 'unsafe-eval' 'unsafe-inline'",

            // Tailwind-Utilities und Filament schreiben Custom Properties
            // direkt in style-Attribute.
            "style-src 'self' 'unsafe-inline'",

            // Schriften liegen ausschließlich lokal — Fredoka und Roboto Mono
            // werden selbst gehostet, kein Google-Fonts-Request.
            "font-src 'self'",

            // blob: wird für Vorschaubilder in Filament-Uploads gebraucht.
            "img-src 'self' data: blob:",

            // Keine Formulare an Fremdziele.
            "form-action 'self'",

            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $regeln);
    }

    private function istHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
