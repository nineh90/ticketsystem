@php
    $zeiten = $this->getZeiten();

    $auffaellig = $zeiten->filter->laeuftAuffaelligLange()->count();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-play-circle"
        :icon-color="$auffaellig > 0 ? 'danger' : 'success'"
        heading="Läuft gerade"
        :description="$auffaellig > 0
            ? $auffaellig . ' ' . ($auffaellig === 1 ? 'Uhr läuft' : 'Uhren laufen') . ' auffällig lange — vermutlich vergessen'
            : 'Zeiterfassungen, die in diesem Moment laufen'"
    >
        {{-- 30 Sekunden: die Dauer rechts soll mitlaufen, ohne dass jemand
             neu lädt. Häufiger wäre Unfug — die Anzeige geht in Minuten. --}}
        @if ($zeiten->isEmpty())
            <p class="py-2 text-sm text-gray-500" wire:poll.30s>
                Gerade läuft keine Uhr mehr. Die Karte verschwindet beim nächsten Laden der Seite.
            </p>
        @else
            @include('filament.laufende-zeiten', ['zeiten' => $zeiten, 'poll' => '30s'])
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
