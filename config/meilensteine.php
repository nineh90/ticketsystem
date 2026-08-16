<?php

/*
 * Vorlagen für Meilensteine.
 *
 * Die immer gleichen Punkte eines Projekts, damit sie nicht bei jedem Kunden
 * neu getippt werden — und, wichtiger, damit sie überall gleich heißen. Zwei
 * Kunden, bei denen derselbe Schritt einmal "Designvorschlag" und einmal
 * "Entwurf" heißt, lesen dasselbe und verstehen Verschiedenes.
 *
 * Die Website-Vorlage ist nicht ausgedacht, sondern der Zeitstrahl, der bei
 * KE!N EINZELFALL tatsächlich entstanden ist — Titel und Texte von dort
 * übernommen, nur der Vereinsname aus dem ersten Punkt genommen. Deshalb der
 * lockere Ton und das "ihr": so ist es geschrieben worden, und ein Kunde
 * merkt sofort, wenn ein Punkt aus einer anderen Feder stammt als der Rest.
 * Wer das ändert, ändert es bitte in allen Vorlagen gemeinsam.
 *
 * Eine Vorlage ist ein Vorschlag, kein Ablauf. Sie wird im Projekt über
 * "Aus Vorlage" angewandt, man wählt dabei ab, was nicht passt, und ergänzt
 * danach von Hand. Nichts passiert automatisch beim Anlegen eines Projekts:
 * ein Zeitstrahl, der ungefragt neun Punkte hat, wird nicht gepflegt,
 * sondern weggeklickt.
 *
 * Ändern: einfach hier. Die Liste wird nirgends in die Datenbank kopiert —
 * angelegte Meilensteine sind ab dem Anlegen eigenständig und ändern sich
 * nicht mit, wenn diese Datei sich ändert. Das ist Absicht: eine Vorlage, die
 * rückwirkend Titel bei laufenden Kundenprojekten umschreibt, wäre eine böse
 * Überraschung.
 */
return [

    /*
     * Welche Vorlage im Formular vorausgewählt ist.
     */
    'vorgabe' => 'website',

    'vorlagen' => [

        'website' => [
            'name' => 'Website',
            'punkte' => [
                [
                    'titel' => 'Erstgespräch',
                    'beschreibung' => 'Wir hören zu: was soll die Webseite können, wen soll sie erreichen und was gefällt euch an dem, was ihr bisher habt.',
                ],
                [
                    'titel' => 'Erstellung eines Angebots',
                    'beschreibung' => 'Nach dem Erstgespräch wissen wir genau, was ihr braucht, und wir erstellen euch ein maßgeschneidertes Angebot.',
                ],
                [
                    'titel' => 'Unser Design Vorschlag',
                    'beschreibung' => 'Während des Gespräches haben wir ganz genau zugehört und eure Webseite schon im Kopf gestylt. Und jetzt stellen wir unsere Idee vor.',
                ],
                [
                    'titel' => 'Planung',
                    'beschreibung' => 'Wir planen alle nötigen Schritte. Prüfen vorhandene Datenbanken und Strukturen auf der Webseite.',
                ],
                [
                    'titel' => 'Beginn der Produktion',
                    'beschreibung' => 'Wir haben jetzt alles, was wir für den Projektstart benötigen. Ihr könnt die Produktion live mitverfolgen und sogar während der Produktion schon Änderungswünsche äußern. Nutzt dazu gerne die Kontakt-Funktion, die bestehenden Tickets oder erstellt uns ein Ticket und wir antworten zeitnah.',
                ],
                [
                    'titel' => 'Seite fertig zum gegenlesen',
                    'beschreibung' => 'Ein großer Teil unserer Arbeit ist erledigt, jetzt seid ihr an der Reihe. Bitte überprüft das Design, die Texte und klickt euch einmal komplett durch eure neue Webseite. Falls euch etwas nicht gefällt, macht ein Screenshot und schickt uns direkt hier im Kundenbereich ein Ticket. So könnt ihr uns immer und zu jeder Zeit bei der Entwicklung helfen.',
                ],
                [
                    'titel' => 'Wir bearbeiten eure Änderungswünsche',
                    'beschreibung' => 'Ihr habt die Webseite geprüft und euch sind noch Fehler oder Verbesserungsvorschläge aufgefallen. Kein Problem, wir setzen gerade alle eure Änderungswünsche um :)',
                ],
                [
                    'titel' => 'Finale Erklärung der Webseite - Abnahme',
                    'beschreibung' => 'Wir haben alle wichtigen Arbeiten abgeschlossen und würden euch gerne eure Webseite in einem Video-Call erklären.',
                ],
                [
                    'titel' => 'Webseite ist Live',
                    'beschreibung' => 'Juhu.. wir haben es geschafft. Eure neue Webseite ist auf der bekannten Domain online.',
                ],
            ],
        ],

        'app' => [
            'name' => 'App',
            'punkte' => [
                [
                    'titel' => 'Erstgespräch',
                    'beschreibung' => 'Wir hören zu: was soll die App leisten, wer benutzt sie täglich und was nervt euch an dem Weg, den ihr bisher geht.',
                ],
                [
                    'titel' => 'Erstellung eines Angebots',
                    'beschreibung' => 'Nach dem Erstgespräch wissen wir genau, was ihr braucht, und wir erstellen euch ein maßgeschneidertes Angebot.',
                ],
                [
                    'titel' => 'Konzept und Abläufe',
                    'beschreibung' => 'Wir halten fest, welche Schritte die App abbildet und wie ihr euch durch sie bewegt — bevor gebaut wird. Hier ist Ändern noch billig.',
                ],
                [
                    'titel' => 'Unser Design Vorschlag',
                    'beschreibung' => 'Die ersten Bildschirme zum Draufschauen. Sagt uns ruhig, was euch nicht gefällt — dafür ist der Schritt da.',
                ],
                [
                    'titel' => 'Beginn der Produktion',
                    'beschreibung' => 'Wir bauen. Ihr könnt jederzeit ein Ticket schreiben, wenn euch unterwegs etwas einfällt.',
                ],
                [
                    'titel' => 'Testlauf',
                    'beschreibung' => 'Ihr probiert die App im Alltag aus. Was hakt, was fehlt, was versteht niemand? Schickt es uns direkt hier im Kundenbereich.',
                ],
                [
                    'titel' => 'Wir bearbeiten eure Änderungswünsche',
                    'beschreibung' => 'Ihr habt die App ausprobiert und euch sind noch Fehler oder Verbesserungsvorschläge aufgefallen. Kein Problem, wir setzen gerade alle eure Änderungswünsche um :)',
                ],
                [
                    'titel' => 'App ist Live',
                    'beschreibung' => 'Juhu.. wir haben es geschafft. Die App steht allen zur Verfügung, für die sie gedacht ist.',
                ],
            ],
        ],

        'betreuung' => [
            'name' => 'Betreuung',
            'punkte' => [
                [
                    'titel' => 'Übergabe',
                    'beschreibung' => 'Ihr wisst, wo was liegt, wie ihr uns erreicht und was im Fall der Fälle passiert. Alle Zugänge findet ihr hier im Kundenbereich.',
                ],
                [
                    'titel' => 'Einweisung',
                    'beschreibung' => 'Wir zeigen euch in Ruhe, was ihr selbst ändern könnt — und wo ihr uns besser einmal kurz fragt.',
                ],
                [
                    'titel' => 'Erster Wartungslauf',
                    'beschreibung' => 'Aktualisierungen eingespielt, Sicherung geprüft, alles läuft. Ab hier passiert das regelmäßig im Hintergrund.',
                ],
            ],
        ],

    ],

];
