{{--
    Das Fenster hinter der Zeit-Kachel.

    Liegt unter views/filament/, weil nur dieser Ordner als @source im Theme
    steht — eine Ebene höher blieben die Klassen hier ungefärbt.

    Erwartet eine Livewire-Komponente mit dem Trait OeffnetDasLogbuch.
--}}
<div
    {{-- Beim Schließen die Einträge wieder abwerfen. Das Ereignis geht an alle
         Fenster der Seite, deshalb der Vergleich auf die eigene Kennung.

         Erst nach dem Zufahren: das Fenster blendet aus, und wer sofort
         abwirft, sieht in dieser knappen halben Sekunde die Liste noch einmal
         zu "Wird geholt …" umspringen. --}}
    x-on:close-modal.window="if ($event.detail?.id === @js($this->logbuchId())) setTimeout(() => $wire.logbuchSchliessen(), 400)"
>
    <x-filament::modal
        :id="$this->logbuchId()"
        width="2xl"
        icon="heroicon-o-clock"
        icon-color="info"
        :heading="$this->logbuchTitel()"
        :description="$this->logbuchBeschreibung()"
    >
        @php
            $zeiten = $this->getLogbuch();
        @endphp

        @if (! $this->logbuchGeladen)
            {{-- Der Weg vom Klick bis hierher ist eine Server-Antwort lang.
                 Ohne diese Zeile stünde das Fenster in der Zeit leer da und
                 sähe aus, als sei nichts erfasst worden. --}}
            <p class="py-6 text-center text-sm text-gray-500">
                Wird geholt …
            </p>
        @elseif ($zeiten->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500">
                Hier ist noch nichts erfasst.
            </p>
        @else
            @include('filament.logbuch-liste', [
                'zeiten' => $zeiten,
                'mitNamen' => $this->logbuchMitNamen(),
            ])
        @endif
    </x-filament::modal>
</div>
