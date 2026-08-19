<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die drei Vorlagen aus der Konfiguration in die Tabellen holen.
 *
 * Die Texte stehen hier ausgeschrieben und werden nicht aus config gelesen:
 * ab dieser Migration ist die Datenbank die Wahrheit, und ein Rückgriff auf
 * eine Datei, die es gleich nicht mehr gibt, wäre nur eine Fußangel.
 *
 * **Vier Korrekturen sind dabei eingeflossen**, alle an Stellen, die der
 * Kunde wörtlich liest:
 *
 * 1. **"Ticket" heißt beim Kunden "Anliegen".** Der Text schickte ihn
 *    viermal auf einen Knopf, den es in seinem Bereich gar nicht gibt — das
 *    Wort steht ausschließlich bei uns. Der schwerste der vier.
 * 2. "zum gegenlesen" → "zum Gegenlesen" (Substantiv).
 * 3. "Unser Design Vorschlag" → "Unser Designvorschlag".
 * 4. "Kontakt-Funktion" → die Bereiche, die es wirklich gibt.
 *
 * Nicht angefasst: "Webseite" im Fließtext, obwohl die Vorlage "Website"
 * heißt. Beides ist richtiges Deutsch, und der Ton stammt aus dem
 * KE!N-EINZELFALL-Projekt — das ist Nils' Stimme, nicht meine. Ab jetzt
 * lässt sich das ohnehin im Maschinenraum ändern.
 *
 * Läuft nur, wenn die Tabelle leer ist: sonst überschriebe ein erneuter
 * Durchlauf von Hand geänderte Texte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('reiseplan_vorlagen')->exists()) {
            return;
        }

        $jetzt = now();

        foreach ($this->vorlagen() as $sortierung => $vorlage) {
            $vorlageId = DB::table('reiseplan_vorlagen')->insertGetId([
                'name' => $vorlage['name'],
                'schluessel' => $vorlage['schluessel'],
                'sortierung' => $sortierung + 1,
                'ist_vorgabe' => $vorlage['schluessel'] === 'website',
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);

            foreach ($vorlage['punkte'] as $i => [$titel, $beschreibung]) {
                DB::table('reiseplan_punkte')->insert([
                    'reiseplan_vorlage_id' => $vorlageId,
                    'titel' => $titel,
                    'beschreibung' => $beschreibung,
                    'sortierung' => $i + 1,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('reiseplan_punkte')->delete();
        DB::table('reiseplan_vorlagen')->delete();
    }

    /** @return array<int, array{name: string, schluessel: string, punkte: array<int, array{0: string, 1: string}>}> */
    private function vorlagen(): array
    {
        return [
            [
                'name' => 'Website',
                'schluessel' => 'website',
                'punkte' => [
                    ['Erstgespräch', 'Wir hören zu: was soll die Webseite können, wen soll sie erreichen und was gefällt euch an dem, was ihr bisher habt.'],
                    ['Erstellung eines Angebots', 'Nach dem Erstgespräch wissen wir genau, was ihr braucht, und wir erstellen euch ein maßgeschneidertes Angebot.'],
                    ['Unser Designvorschlag', 'Während des Gespräches haben wir ganz genau zugehört und eure Webseite schon im Kopf gestylt. Und jetzt stellen wir unsere Idee vor.'],
                    ['Planung', 'Wir planen alle nötigen Schritte. Prüfen vorhandene Datenbanken und Strukturen auf der Webseite.'],
                    ['Beginn der Produktion', 'Wir haben jetzt alles, was wir für den Projektstart benötigen. Ihr könnt die Produktion live mitverfolgen und sogar während der Produktion schon Änderungswünsche äußern. Nutzt dazu gerne die Nachrichten, ein bestehendes Anliegen oder meldet uns ein neues — wir antworten zeitnah.'],
                    ['Seite fertig zum Gegenlesen', 'Ein großer Teil unserer Arbeit ist erledigt, jetzt seid ihr an der Reihe. Bitte überprüft das Design, die Texte und klickt euch einmal komplett durch eure neue Webseite. Falls euch etwas nicht gefällt, macht einen Screenshot und meldet uns hier im Kundenbereich ein Anliegen. So könnt ihr uns immer und zu jeder Zeit bei der Entwicklung helfen.'],
                    ['Wir bearbeiten eure Änderungswünsche', 'Ihr habt die Webseite geprüft und euch sind noch Fehler oder Verbesserungsvorschläge aufgefallen. Kein Problem, wir setzen gerade alle eure Änderungswünsche um :)'],
                    ['Finale Erklärung der Webseite – Abnahme', 'Wir haben alle wichtigen Arbeiten abgeschlossen und würden euch gerne eure Webseite in einem Video-Call erklären.'],
                    ['Webseite ist Live', 'Juhu … wir haben es geschafft. Eure neue Webseite ist auf der bekannten Domain online.'],
                ],
            ],
            [
                'name' => 'App',
                'schluessel' => 'app',
                'punkte' => [
                    ['Erstgespräch', 'Wir hören zu: was soll die App leisten, wer benutzt sie täglich und was nervt euch an dem Weg, den ihr bisher geht.'],
                    ['Erstellung eines Angebots', 'Nach dem Erstgespräch wissen wir genau, was ihr braucht, und wir erstellen euch ein maßgeschneidertes Angebot.'],
                    ['Konzept und Abläufe', 'Wir halten fest, welche Schritte die App abbildet und wie ihr euch durch sie bewegt — bevor gebaut wird. Hier ist Ändern noch billig.'],
                    ['Unser Designvorschlag', 'Die ersten Bildschirme zum Draufschauen. Sagt uns ruhig, was euch nicht gefällt — dafür ist der Schritt da.'],
                    ['Beginn der Produktion', 'Wir bauen. Ihr könnt uns jederzeit ein Anliegen melden, wenn euch unterwegs etwas einfällt.'],
                    ['Testlauf', 'Ihr probiert die App im Alltag aus. Was hakt, was fehlt, was versteht niemand? Schickt es uns direkt hier im Kundenbereich.'],
                    ['Wir bearbeiten eure Änderungswünsche', 'Ihr habt die App ausprobiert und euch sind noch Fehler oder Verbesserungsvorschläge aufgefallen. Kein Problem, wir setzen gerade alle eure Änderungswünsche um :)'],
                    ['App ist Live', 'Juhu … wir haben es geschafft. Die App steht allen zur Verfügung, für die sie gedacht ist.'],
                ],
            ],
            [
                'name' => 'Betreuung',
                'schluessel' => 'betreuung',
                'punkte' => [
                    ['Übergabe', 'Ihr wisst, wo was liegt, wie ihr uns erreicht und was im Fall der Fälle passiert. Alle Zugänge findet ihr hier im Kundenbereich.'],
                    ['Einweisung', 'Wir zeigen euch in Ruhe, was ihr selbst ändern könnt — und wo ihr uns besser einmal kurz fragt.'],
                    ['Erster Wartungslauf', 'Aktualisierungen eingespielt, Sicherung geprüft, alles läuft. Ab hier passiert das regelmäßig im Hintergrund.'],
                ],
            ],
        ];
    }
};
