{{--
    Der Verlauf des Kunden mit uns. Eine Fläche, kein Auswahlmenü daneben —
    Begründung in der Seitenklasse.
--}}
@php
    $ich = auth()->user();
    $unterhaltung = $this->verlauf();
@endphp

<x-filament-panels::page>
    <x-filament::section
        icon="heroicon-o-chat-bubble-left-right"
        heading="Verlauf"
        description="Alles hier bleibt zwischen Ihnen und uns — es taucht in keinem Anliegen auf."
    >
        @include('filament.unterhaltung', ['unterhaltung' => $unterhaltung, 'ich' => $ich])
    </x-filament::section>
</x-filament-panels::page>
