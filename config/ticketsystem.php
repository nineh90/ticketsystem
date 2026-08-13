<?php

return [

    /*
     * Token für POST /api/v1/tickets (Header: Authorization: Bearer <TOKEN>).
     * Erzeugen mit:  php artisan ticket:token
     *
     * Bleibt der Wert leer, antwortet die Schnittstelle mit 503 statt jeden
     * hereinzulassen.
     */
    'api_token' => env('TICKET_API_TOKEN', ''),

];
