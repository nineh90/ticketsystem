@php
    $zeiten = $this->getZeiten();

    $auffaellig = $zeiten->filter->laeuftAuffaelligLange()->count();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-play-circle"
        :icon-color="$auffaellig > 0 ? 'danger' : 'success'"
        heading="Deine Uhr läuft"
        :description="$auffaellig > 0
            ? 'Sie läuft auffällig lange — vermutlich vergessen'
            : 'Vergiss nicht zu stoppen, wenn du fertig bist'"
    >
        @if ($zeiten->isEmpty())
            <p class="py-2 text-sm text-gray-500" wire:poll.30s>
                Deine Uhr läuft nicht mehr. Die Karte verschwindet beim nächsten Laden der Seite.
            </p>
        @else
            @include('filament.laufende-zeiten', ['zeiten' => $zeiten, 'poll' => '30s'])
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
