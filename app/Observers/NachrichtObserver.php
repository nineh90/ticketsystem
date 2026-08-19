<?php

namespace App\Observers;

use App\Enums\MailEreignis;
use App\Filament\Kunde\Pages\Nachrichten as KundenNachrichten;
use App\Filament\Pages\Nachrichten as InterneNachrichten;
use App\Models\Nachricht;
use App\Models\User;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Was nach einer geschriebenen Nachricht passieren muss.
 *
 * Am Observer und nicht in der Oberfläche: geschrieben wird aus zwei Panels,
 * und alles hier — der Zeitstempel für die Sortierung, der eigene Lesestand,
 * die Glocke beim Gegenüber — müsste sonst an beiden Stellen stehen. Die
 * zweite Stelle ist die, die es beim nächsten Umbau vergisst.
 *
 * Ohne die Glocke wäre der ganze Chat eine Anzeigetafel, vor der man täglich
 * stehen bleiben muss. Der Kunde schreibt einmal, hört nichts, und ruft beim
 * dritten Mal an — dann hätte man es lassen können.
 */
class NachrichtObserver
{
    public function created(Nachricht $nachricht): void
    {
        $nachricht->loadMissing(['unterhaltung.customer', 'absender']);

        $unterhaltung = $nachricht->unterhaltung;
        $absender = $nachricht->absender;

        if ($unterhaltung === null) {
            return;
        }

        // Hebt den Faden in der Liste nach oben und holt ihn überhaupt erst
        // hinein — siehe scopeBegonnen.
        $unterhaltung->forceFill(['letzte_nachricht_am' => $nachricht->created_at])->save();

        if ($absender === null) {
            return;
        }

        // Wer schreibt, hat gelesen. Ohne das stünde die eigene Antwort in
        // der Übersicht neben einer Zahl ungelesener Nachrichten, die aus
        // dem stammt, was man gerade beantwortet hat.
        $unterhaltung->alsGelesenMarkieren($absender);

        $empfaenger = $unterhaltung->empfaenger($absender);

        if ($empfaenger->isEmpty()) {
            return;
        }

        // Getrennt, weil der Knopf in der Meldung ins jeweils eigene Panel
        // führen muss. Ein Kunde, der auf eine Adresse im internen Panel
        // klickt, landet auf der internen Anmeldung — und hält sein Konto
        // für kaputt.
        [$aussen, $innen] = $empfaenger->partition(fn (User $nutzer) => $nutzer->istKunde());

        $this->melden($innen, $this->titelFuerUns($nachricht), $nachricht, InterneNachrichten::getUrl(
            ['unterhaltung' => $unterhaltung->getKey()],
            panel: 'admin',
        ));

        $this->melden($aussen, 'Neue Nachricht von '.config('kontakt.name'), $nachricht, KundenNachrichten::getUrl(
            panel: 'kunde',
        ));
    }

    /**
     * Wie die Meldung bei uns heißt.
     *
     * Bei einem Kundenfaden zählt der Kunde, nicht die Person, die getippt
     * hat: "Nachricht von Müller GmbH" sagt einem Mitarbeiter etwas, "von
     * Frau Berger" erst nach kurzem Nachdenken. Der Name der Person steht
     * darunter im Text.
     */
    private function titelFuerUns(Nachricht $nachricht): string
    {
        $unterhaltung = $nachricht->unterhaltung;

        if ($unterhaltung->istIntern()) {
            return 'Nachricht von '.($nachricht->absender?->name ?? 'einem Kollegen');
        }

        return 'Nachricht von '.($unterhaltung->customer?->name ?? 'einem Kunden');
    }

    /** @param  Collection<int, User>  $empfaenger */
    private function melden(Collection $empfaenger, string $titel, Nachricht $nachricht, string $url): void
    {
        if ($empfaenger->isEmpty()) {
            return;
        }

        Benachrichtigung::an(
            $empfaenger,
            Notification::make()
                ->title($titel)
                ->body($nachricht->auszug())
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->actions([
                    Benachrichtigung::knopf('Lesen', $url),
                ]),
            // Damit die Meldung verstummt, sobald jemand den Verlauf öffnet —
            // und nicht erst, wenn er sie zusätzlich in der Glocke wegklickt.
            Herkunft::unterhaltung($nachricht->unterhaltung_id),
            // Der Kunde nur beim Kundenverlauf — ein interner Faden gehoert
            // keinem, und sein Logo in der Mail waere eine falsche Fährte.
            $nachricht->unterhaltung?->customer,
            MailEreignis::Nachricht,
        );
    }
}
