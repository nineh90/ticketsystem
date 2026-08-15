<?php

/*
 * Wo unsere Vorschauen liegen.
 *
 * Der Vorschlag, den der Knopf neben "Vorschau" im Projektformular einsetzt,
 * wenn beim Kunden nichts Eigenes hinterlegt ist. {projekt} wird durch das
 * Kürzel des Projekts ersetzt.
 *
 * Als Konfiguration und nicht je Kunde, weil es für die meisten dasselbe ist:
 * die Vorschau läuft auf unserem Server. Nur wo es anders ist — ein Kunde,
 * dessen Demo bei ihm selbst liegt, oder einer mit einer gewachsenen eigenen
 * Adresse — steht es am Kunden und sticht diesen Wert. Andersherum, also je
 * Kunde pflegen und die Regel nur im Kopf haben, hieße dieselbe Zeile fünfmal
 * einzutragen und beim Serverumzug fünfmal zu ändern.
 *
 * Auf null gesetzt verschwindet der Knopf bei Kunden ohne eigene Adresse —
 * dann tippt man die Vorschau-Adresse eben von Hand ein.
 */
return [
    'muster' => env('DEMO_MUSTER', '{projekt}.nils-digital.de'),
];
