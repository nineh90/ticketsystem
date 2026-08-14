<?php

/*
 * Die Kontaktdaten, die im Kundenbereich stehen.
 *
 * Als Konfiguration und nicht fest in der Ansicht: sie stehen auch auf
 * nils-digital.de und im Impressum, und wenn sich eine Nummer ändert, soll
 * man sie nicht in einer Blade-Datei suchen müssen. Über die .env
 * überschreibbar, damit lokal keine echte Telefonnummer im Testbetrieb steht.
 */
return [
    'name' => env('KONTAKT_NAME', 'Nils-Digital'),
    'email' => env('KONTAKT_EMAIL', 'info@nils-digital.de'),
    'telefon' => env('KONTAKT_TELEFON', null),
    'website' => env('KONTAKT_WEBSITE', 'https://nils-digital.de'),

    /*
     * Wann wir in der Regel antworten. Bewusst eine Angabe in Werktagen und
     * keine Uhrzeit: eine Zusage, die man an einem vollen Tag nicht halten
     * kann, ist schlechter als gar keine.
     */
    'reaktionszeit' => env('KONTAKT_REAKTIONSZEIT', 'in der Regel innerhalb eines Werktages'),
];
