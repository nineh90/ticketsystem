<?php

namespace App\Policies;

use App\Models\Dokument;
use App\Models\User;

/**
 * Wer welches Dokument bekommt.
 *
 * Die Policy bewacht vor allem die Ausliefer-Route: die Dateien liegen
 * außerhalb von public/, und ob jemand ein PDF herunterladen darf,
 * entscheidet ausschließlich diese Klasse. Ein Angebot ist eine Zahl, die
 * niemanden außer dem Empfänger etwas angeht.
 *
 * Für den Kundenzugang gelten zwei Bedingungen gleichzeitig — sein eigener
 * Kunde und freigegeben. Die zweite steht hier ein zweites Mal, obwohl
 * Dokument::sichtbarFuer sie schon kennt: der Scope füllt Listen, diese
 * Klasse bewacht den Direktaufruf. Eine erratene Adresse geht nicht durch
 * eine Liste.
 */
class DokumentPolicy
{
    public function view(User $user, Dokument $dokument): bool
    {
        if ($user->istKunde()) {
            return $dokument->kunden_sichtbar
                && $dokument->customer_id === $user->customer_id;
        }

        return $user->can('view', $dokument->customer);
    }

    public function create(User $user): bool
    {
        // Kunden laden hier nichts hoch. Was sie uns schicken wollen, gehört
        // als Anhang an ein Anliegen — dort ist der Zusammenhang, in dem es
        // jemand liest.
        return ! $user->istKunde();
    }

    public function update(User $user, Dokument $dokument): bool
    {
        return ! $user->istKunde() && $user->can('view', $dokument->customer);
    }

    /** Löschen nimmt die Datei mit — deshalb nur der Admin und der Hochlader. */
    public function delete(User $user, Dokument $dokument): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($dokument->hochgeladenVon);
    }

    /**
     * Zusagen oder ablehnen darf nur der Kunde selbst, und nur solange die
     * Frage offen ist. Intern ändert man den Stand über das Formular — dann
     * bleibt der Zeitstempel der Kundenantwort leer, und genau daran ist
     * hinterher zu erkennen, wer entschieden hat.
     */
    public function beantworten(User $user, Dokument $dokument): bool
    {
        return $user->istKunde()
            && $dokument->customer_id === $user->customer_id
            && $dokument->wartetAufAntwort();
    }
}
