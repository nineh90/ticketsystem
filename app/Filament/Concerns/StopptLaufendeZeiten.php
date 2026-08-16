<?php

namespace App\Filament\Concerns;

use App\Models\TimeEntry;
use App\Support\Dauer;
use Filament\Notifications\Notification;

/**
 * Der Stopp-Knopf aus der Liste der laufenden Uhren.
 *
 * Als Trait, weil dieselbe Liste an zwei Stellen steht — über der Zeitentabelle
 * eines Tickets und auf dem Dashboard — und beide Male aus einer Livewire-
 * Komponente heraus geklickt wird. Ohne die gemeinsame Methode hätte jede
 * Stelle ihre eigene Fassung samt eigener Rechteprüfung.
 */
trait StopptLaufendeZeiten
{
    /**
     * Eine laufende Buchung beenden — auch eine fremde, wenn man darf.
     *
     * Genau dafür ist die Liste da: die vergessene Uhr eines Kollegen sieht
     * man selbst oft eher als er. Wer sie stoppen darf, sagt die
     * TimeEntryPolicy — die eigene immer, fremde nur als Administrator.
     */
    public function zeitStoppen(int $eintrag): void
    {
        $zeit = TimeEntry::query()->laufend()->with('user', 'ticket')->find($eintrag);

        // Kein Fehler, sondern der Normalfall bei zwei offenen Fenstern: der
        // andere war schneller. Die Liste ist danach ohnehin aktuell.
        if ($zeit === null) {
            Notification::make()
                ->title('Diese Uhr läuft nicht mehr')
                ->warning()
                ->send();

            return;
        }

        if (! auth()->user()?->can('update', $zeit)) {
            Notification::make()
                ->title('Fremde Zeiten darf nur ein Administrator stoppen')
                ->danger()
                ->send();

            return;
        }

        $wer = $zeit->user;

        $zeit->stoppen();

        Notification::make()
            ->title('Zeit erfasst: '.Dauer::alsStunden($zeit->minuten))
            ->body($wer?->is(auth()->user())
                ? $zeit->ticket?->kennung()
                : trim(($wer?->name ?? 'Jemand').' · '.$zeit->ticket?->kennung(), ' ·'))
            ->success()
            ->send();
    }
}
