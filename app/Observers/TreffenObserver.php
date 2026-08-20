<?php

namespace App\Observers;

use App\Models\Treffen;
use App\Support\Messe;

/**
 * Was passiert, wenn sich an einem Treffen etwas tut.
 *
 * Vier Anlässe: angelegt und freigegeben (das ist die Einladung), abgesagt,
 * verschoben. Aus Sicht des Kunden ist alles derselbe Satz — "an dem Termin
 * hat sich etwas getan" —, deshalb eine Klasse und nicht vier Stellen; ein
 * fünfter Anlass käme sonst irgendwo dazu und niemand fände die anderen.
 *
 * Die Meldung nach außen hängt an kunden_sichtbar. Solange der Schalter aus
 * ist, ist ein Treffen ein Bleistiftstrich in unserem Kalender, und ein
 * Bleistiftstrich lädt niemanden ein. Wen genau sie erreicht, entscheidet
 * Messe — hier steht nur, wann sie ausgelöst wird.
 *
 * Nicht hier steht die Erinnerung vor dem Termin: die hat keinen Auslöser am
 * Datensatz, sondern einen an der Uhr, und läuft deshalb über den Planer
 * (Console\Commands\TreffenErinnern).
 */
class TreffenObserver
{
    public function created(Treffen $treffen): void
    {
        if (! $treffen->kunden_sichtbar) {
            return;
        }

        Messe::anKunden($treffen, 'Sie sind eingeladen');
    }

    public function updated(Treffen $treffen): void
    {
        // Die Absage zuerst: sie ist die Änderung, die am dringendsten
        // ankommen muss, und sie darf nicht als "verschoben" durchgehen.
        if ($treffen->wasChanged('abgesagt_at') && $treffen->istAbgesagt()) {
            Messe::anKunden($treffen, 'Termin abgesagt', 'warning');

            // Die Crew erfährt es unabhängig von der Freigabe: ein noch
            // nicht verschickter Termin steht trotzdem in ihrem Kalender.
            Messe::anCrew($treffen, null, 'Abgesagt', 'warning');

            return;
        }

        // Frisch freigegeben — das ist die eigentliche Einladung, denn beim
        // Anlegen stand der Schalter meistens noch auf aus.
        if ($treffen->wasChanged('kunden_sichtbar') && $treffen->kunden_sichtbar) {
            Messe::anKunden($treffen, 'Sie sind eingeladen');

            return;
        }

        // Verschoben. Nur der Termin selbst löst aus: an einem getippten
        // Wort in der Notiz hat niemand Interesse, und eine Meldung für
        // jede Kleinigkeit ist eine, die bald übergangen wird.
        if (! $treffen->wasChanged('beginnt_am')) {
            return;
        }

        // Ein verschobener Termin ist für die Erinnerungen ein neuer. Ohne
        // diese Zeile behielte er seine Stempel: wer den Termin von heute auf
        // nächste Woche legt, bekäme dann nie wieder eine Erinnerung dazu —
        // die beiden Stufen gelten laut Datenbank als erledigt.
        Messe::erinnerungenZuruecksetzen($treffen);

        // Die Crew zuerst und ohne Bedingung — auch ein interner Termin
        // steht in ihrem Kalender und muss dort umziehen.
        Messe::anCrew($treffen, null, 'Verschoben');

        Messe::anKunden($treffen, 'Neuer Termin');
    }
}
