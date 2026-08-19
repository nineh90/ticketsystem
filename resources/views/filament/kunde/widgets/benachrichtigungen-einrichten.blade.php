{{--
    Zwei Zustände, eine Karte: die Frage selbst und die Erinnerung an eine
    Bestätigung, die noch aussteht.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :icon="$this->wartetAufBestaetigung() ? 'heroicon-o-clock' : 'heroicon-o-bell-alert'"
        :heading="$this->wartetAufBestaetigung()
            ? 'Fast geschafft'
            : 'Möchten Sie informiert werden?'"
    >
        @if ($this->wartetAufBestaetigung())
            <p class="text-sm text-gray-300">
                Wir haben Ihnen einen Bestätigungslink an
                <strong class="font-mono">{{ $this->adresse() }}</strong> geschickt.
                Erst nach einem Klick darin schreiben wir Ihnen — schauen Sie notfalls
                im Spam-Ordner nach.
            </p>

            <div class="mt-4">
                <x-filament::button tag="a" :href="$this->kontoUrl()" color="gray" size="sm">
                    Adresse ändern oder erneut senden
                </x-filament::button>
            </div>
        @else
            <p class="text-sm text-gray-300">
                Wenn Sie möchten, schreiben wir Ihnen eine E-Mail, sobald sich bei Ihren
                Anliegen etwas tut — wenn wir antworten oder wenn etwas erledigt ist.
                Sie sagen uns, an welche Adresse und worüber.
            </p>

            <p class="mt-2 text-sm text-gray-400">
                Wichtig: Es muss eine Adresse sein, die Sie <strong>tatsächlich abrufen
                können</strong> — wir schicken einen Bestätigungslink dorthin, und erst
                Ihr Klick darin schaltet es frei.
            </p>

            <p class="mt-2 text-xs text-gray-500">
                Ohne Ihre Zustimmung schicken wir Ihnen nichts. Sie können das jederzeit
                wieder abschalten.
            </p>

            <div class="mt-4">
                <x-filament::button tag="a" :href="$this->kontoUrl()" size="sm">
                    Jetzt einrichten
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
