<?php

return [

    /*
     * Token für n8n an /api/v1/… (Header: Authorization: Bearer <TOKEN>).
     * Erzeugen mit:  php artisan ticket:token
     *
     * Bleibt der Wert leer, antwortet die Schnittstelle mit 503 statt jeden
     * hereinzulassen.
     */
    'api_token' => env('TICKET_API_TOKEN', ''),

    /*
     * Eigener Token für die Website nils-digital.de. Erzeugen mit:
     *   php artisan ticket:token --website
     *
     * Getrennt vom Token oben, damit sich jeder der beiden einzeln
     * widerrufen lässt, und weil am Token hängt, welche Quelle ein Ticket
     * trägt: mit diesem hier "website", mit dem oberen "api".
     *
     * Bleibt der Wert leer, gilt einfach nur der obere.
     */
    'api_token_website' => env('TICKET_API_TOKEN_WEBSITE', ''),

    /*
     * Slug des Projekts, in dem die Anfragen der Website ankommen. Danach
     * zählt GET /api/v1/uebersicht die Zahl "anfragen_offen".
     */
    'anfragen_projekt' => 'anfragen',

];
