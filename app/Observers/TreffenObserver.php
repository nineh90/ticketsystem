<?php

namespace App\Observers;

use App\Enums\MailEreignis;
use App\Filament\Kunde\Pages\Uebersicht;
use App\Models\Treffen;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use App\Support\Messe;
use Filament\Notifications\Notification;

/**
 * Sagt dem Kunden Bescheid, wenn ein Treffen ansteht oder sich ändert.
 *
 * Drei Anlässe, und alle drei sind derselbe Satz aus seiner Sicht: "an dem
 * Termin hat sich etwas getan". Deshalb eine Klasse und nicht drei Stellen —
 * ein vierter Anlass käme sonst irgendwo dazu und niemand fände die anderen.
 *
 * Alles hängt an kunden_sichtbar. Solange der Schalter aus ist, ist ein
 * Treffen ein Bleistiftstrich in unserem Kalender, und ein Bleistiftstrich
 * lädt niemanden ein.
 */
class TreffenObserver
{
    public function created(Treffen $treffen): void
    {
        if (! $treffen->kunden_sichtbar) {
            return;
        }

        $this->melden($treffen, 'Sie sind eingeladen');
    }

    public function updated(Treffen $treffen): void
    {
        // Die Absage zuerst: sie ist die Änderung, die am dringendsten
        // ankommen muss, und sie darf nicht als "verschoben" durchgehen.
        if ($treffen->wasChanged('abgesagt_at') && $treffen->istAbgesagt()) {
            if ($treffen->kunden_sichtbar) {
                $this->melden($treffen, 'Termin abgesagt', 'warning');
            }

            // Die Crew erfährt es unabhängig von der Freigabe: ein noch
            // nicht verschickter Termin steht trotzdem in ihrem Kalender.
            Messe::anCrew($treffen, null, 'Abgesagt', 'warning');

            return;
        }

        // Frisch freigegeben — das ist die eigentliche Einladung, denn beim
        // Anlegen stand der Schalter meistens noch auf aus.
        if ($treffen->wasChanged('kunden_sichtbar') && $treffen->kunden_sichtbar) {
            $this->melden($treffen, 'Sie sind eingeladen');

            return;
        }

        // Verschoben. Nur der Termin selbst löst aus: an einem getippten
        // Wort in der Notiz hat niemand Interesse, und eine Meldung für
        // jede Kleinigkeit ist eine, die bald übergangen wird.
        if (! $treffen->wasChanged('beginnt_am')) {
            return;
        }

        // Die Crew zuerst und ohne Bedingung — auch ein interner Termin
        // steht in ihrem Kalender und muss dort umziehen.
        Messe::anCrew($treffen, null, 'Verschoben');

        if ($treffen->kunden_sichtbar) {
            $this->melden($treffen, 'Neuer Termin');
        }
    }

    private function melden(Treffen $treffen, string $anlass, string $farbe = 'info'): void
    {
        $treffen->loadMissing('customer');

        if ($treffen->customer === null) {
            return;
        }

        $schiff = (string) config('kontakt.schiff');

        Benachrichtigung::an(
            Benachrichtigung::kundenzugaenge($treffen->customer_id),
            Notification::make()
                ->title($anlass.': '.$treffen->titel)
                ->body(Messe::wann($treffen).' an Bord der '.$schiff)
                ->icon('heroicon-o-video-camera')
                ->color($farbe)
                ->actions([
                    Benachrichtigung::knopf('Ansehen', Uebersicht::getUrl(panel: 'kunde')),
                ]),
            Herkunft::treffen($treffen),
            $treffen->customer,
            MailEreignis::Treffen,
        );
    }
}
