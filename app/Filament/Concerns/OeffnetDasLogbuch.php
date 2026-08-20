<?php

namespace App\Filament\Concerns;

use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/**
 * Die Zeit-Kachel als Knopf: ein Klick, und darunter steht, wer wann was
 * gemacht hat.
 *
 * Als Trait, weil beide Überblicke dieselbe Mechanik brauchen und sich nur in
 * der Menge unterscheiden — auf der Brücke der ganze Betrieb von heute, auf
 * der Wache die eigene Woche. Was gezeigt wird, sagt das Widget über
 * logbuchZeiten(); alles andere ist hier gleich.
 *
 * Zwei Wege beim Klick, und beide werden gebraucht: Alpine öffnet das Fenster
 * sofort (x-on:click), Livewire holt die Einträge nach (wire:click). Umgekehrt
 * — erst holen, dann öffnen — hinge das Fenster an einer Server-Antwort, und
 * eine Kachel, die auf den Klick eine halbe Sekunde nichts tut, klickt man ein
 * zweites Mal.
 */
trait OeffnetDasLogbuch
{
    /**
     * Erst nach dem ersten Klick wird abgefragt.
     *
     * Das Fenster steht immer im Seitenquelltext, aber leer. Ohne diesen
     * Schalter liefe die Abfrage bei jedem Aufbau des Dashboards mit — und
     * das Widget zeichnet sich alle fünf Sekunden neu.
     */
    public bool $logbuchGeladen = false;

    public function logbuchOeffnen(): void
    {
        $this->logbuchGeladen = true;
    }

    /**
     * Beim Schließen wieder abwerfen, damit die Abfrage nicht für den Rest
     * des Tages bei jedem Neuzeichnen mitläuft. Beim nächsten Öffnen sind die
     * Zeiten ohnehin frischer.
     */
    public function logbuchSchliessen(): void
    {
        $this->logbuchGeladen = false;
    }

    /** @return Collection<int, TimeEntry> */
    public function getLogbuch(): Collection
    {
        if (! $this->logbuchGeladen) {
            return collect();
        }

        return $this->logbuchZeiten();
    }

    /**
     * Was an die Kachel gehängt wird, damit sie sich wie ein Knopf verhält.
     *
     * role und tabindex, weil aus dem <div> sonst ein Knopf wird, den nur die
     * Maus findet. Ein <button> wäre sauberer, aber das Markup der Kachel
     * kommt aus Filament.
     */
    protected function logbuchKachel(): array
    {
        return [
            'role' => 'button',
            'tabindex' => '0',
            'class' => 'cursor-pointer',
            'wire:click' => 'logbuchOeffnen',
            'x-on:click' => "\$dispatch('open-modal', { id: '".$this->logbuchId()."' })",
            'x-on:keydown.enter' => '$el.click()',
            'x-on:keydown.space.prevent' => '$el.click()',
        ];
    }

    /** Eindeutig je Fenster — daran erkennt es sein open-modal-Ereignis. */
    abstract public function logbuchId(): string;

    abstract public function logbuchTitel(): string;

    abstract public function logbuchBeschreibung(): string;

    /** Steht in jeder Zeile, wer sie gebucht hat? Nur dort, wo es mehrere sind. */
    abstract public function logbuchMitNamen(): bool;

    /** @return Collection<int, TimeEntry> */
    abstract protected function logbuchZeiten(): Collection;
}
