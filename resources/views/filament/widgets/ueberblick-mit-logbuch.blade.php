{{--
    Die Kachelreihe eines Überblicks, plus das Fenster hinter der Zeit-Kachel.

    Bis auf die letzte Zeile die Fassung aus filament-widgets::stats-overview-widget.
    Ein eigener View ist nötig, weil das Fenster in derselben Livewire-Komponente
    stehen muss wie die Kachel, die es öffnet — sonst hört es deren Ereignis nicht
    und wire:click hätte niemanden, der zuhört.

    Erwartet eine Komponente mit dem Trait OeffnetDasLogbuch.
--}}
@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class([
                'fi-wi-stats-overview',
            ])
    "
>
    {{ $this->content }}

    @include('filament.logbuch-fenster')
</x-filament-widgets::widget>
