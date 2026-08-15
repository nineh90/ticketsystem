<?php

/*
 * Wie oft die Glocke nachfragt.
 *
 * Der Wert gilt für beide Panels. Er steht hier und nicht zweimal in den
 * Panel-Providern, weil zwei Zahlen an zwei Orten mit Sicherheit
 * auseinanderlaufen — und dann fragt der Kundenbereich in einem anderen Takt
 * nach als das interne, ohne dass jemand weiß warum.
 *
 * 30 Sekunden ist ein Kompromiss. Die Glocke ist das Einzige, was eine
 * Kundenmeldung von selbst sichtbar macht; wartet sie eine Minute, wirkt das
 * System träge und man drückt F5. Andererseits ist jede Abfrage eine Anfrage
 * je offenem Tab: bei drei Leuten im Haus sind 30 Sekunden nichts, bei
 * fünfzig wäre es eine Überlegung wert.
 *
 * Über die .env zu ändern (GLOCKE_TAKT=15s), ohne dass jemand Code anfassen
 * muss. Auf null gesetzt hört das Nachfragen ganz auf — die Glocke
 * aktualisiert sich dann nur noch beim Seitenwechsel.
 */
return [
    'glocke_takt' => env('GLOCKE_TAKT', '30s'),
];
