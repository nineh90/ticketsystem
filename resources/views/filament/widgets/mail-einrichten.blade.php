{{--
    Die Frage nach Mail-Benachrichtigungen — einmal, auf der Wache.

    Beide Knöpfe beantworten sie und lassen die Karte verschwinden. Ein
    "Später" gibt es bewusst nicht: es bedeutet, dass die Karte morgen wieder
    dasteht, und übermorgen liest sie niemand mehr, obwohl sie noch da ist.

    Anders als beim Kunden wird hier direkt entschieden und nicht auf eine
    Einstellungsseite verwiesen. Der Grund ist der Unterschied im Aufwand:
    ein Kunde muss eine Adresse nennen und bestätigen, intern steht die
    Arbeitsadresse längst fest — da ist die Frage wirklich nur ja oder nein.
--}}
{{-- Das Wurzelelement steht immer da, sonst bricht Livewire ab. --}}
<x-filament-widgets::widget>
    @unless ($beantwortet)
    <x-filament::section
        icon="heroicon-o-bell-alert"
        heading="Willst du Mail bekommen?"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">
            An der Glocke steht ohnehin alles. Zusätzlich können wir dir eine E-Mail
            an <strong class="font-mono">{{ auth()->user()->email }}</strong> schicken,
            sobald etwas hereinkommt — praktisch für alles, was auffallen soll,
            während du nicht im System bist.
        </p>

        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            Das wären: {{ implode(' · ', $this->themen()) }}.
        </p>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
            Einzeln abwählen und jederzeit wieder abschalten kannst du das unter
            <em>Mein Zugang</em>.
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-filament::button
                wire:click="ja"
                icon="heroicon-o-envelope"
            >
                Ja, bitte
            </x-filament::button>

            <x-filament::button
                wire:click="nein"
                color="gray"
            >
                Nein, Glocke reicht
            </x-filament::button>

            <x-filament::button
                tag="a"
                :href="$this->kontoUrl()"
                color="gray"
                size="sm"
                icon="heroicon-o-adjustments-horizontal"
            >
                Selbst auswählen
            </x-filament::button>
        </div>
    </x-filament::section>
    @endunless
</x-filament-widgets::widget>
