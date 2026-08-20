<?php

namespace App\Observers;

use App\Models\TimeEntry;
use App\Support\Automatik;

/**
 * Wer die Uhr startet, arbeitet daran — also steht das Ticket auf "In Arbeit".
 *
 * Der Anlass ist ein Handgriff, den man den ganzen Tag macht und der nichts
 * beiträgt: Uhr starten, dann das Ticket auf dem Deck eine Spalte weiter
 * ziehen. Beides sagt dieselbe Sache, und wer den zweiten Schritt vergisst —
 * und das tut man —, hat ein Brett, das nicht mehr stimmt. Ein Brett, dem man
 * nicht traut, sieht sich niemand mehr an.
 *
 * Am Modell und nicht am Knopf: die Uhr wird heute an einer Stelle gestartet
 * (dem Logbuch am Ticket), aber das war beim Ticket auch einmal so, und
 * inzwischen entsteht eines an vier Stellen. Was an der Tatsache hängt, gilt
 * für jeden Weg dorthin — auch für den, den es noch nicht gibt.
 *
 * Die Regel selbst steht in Support\Automatik, zusammen mit allen anderen:
 * wer wissen will, was das System von selbst tut, soll eine Datei aufschlagen
 * müssen und nicht sieben Observer durchsehen.
 *
 * Ausdrücklich NUR beim Starten einer laufenden Uhr. Ein Nachtrag ("gestern
 * zwei Stunden") beschreibt Vergangenes und darf den Stand von heute nicht
 * umschreiben; sonst zöge das Aufräumen der letzten Woche ein längst
 * erledigtes Ticket zurück aufs Brett.
 */
class TimeEntryObserver
{
    public function created(TimeEntry $zeit): void
    {
        if (! $zeit->laeuft()) {
            return;
        }

        $ticket = $zeit->ticket;

        if ($ticket === null) {
            return;
        }

        Automatik::inArbeit($ticket);
    }
}
