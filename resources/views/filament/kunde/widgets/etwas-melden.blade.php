{{--
    Das Meldeformular auf der Übersicht. Felder und Prüfung kommen aus
    AnliegenForm und LegtAnliegenAn — hier steht nur der Rahmen.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-plus-circle"
        heading="Etwas melden"
        description="Ein Fehler, ein Wunsch oder eine Frage — schreiben Sie es hier auf, wir kümmern uns."
    >
        <form wire:submit="senden">
            {{ $this->form }}

            <div class="mt-6">
                <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:target="senden">
                    Absenden
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
