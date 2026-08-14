{{--
    Kontaktseite des Kundenbereichs.

    Die Reihenfolge ist Absicht: erst der Weg, der im System landet, dann die
    beiden, die es nicht tun. Wer die Seite überfliegt, greift zum ersten
    Angebot — und das soll das sein, bei dem hinterher jemand nachlesen kann,
    was besprochen wurde.
--}}
@php
    $kontakt = $this->getKontaktdaten();
@endphp

<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Der bevorzugte Weg, deshalb doppelt so breit wie die anderen. --}}
        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">Ein Anliegen anlegen</x-slot>
            <x-slot name="description">
                Der Weg, bei dem nichts verloren geht.
            </x-slot>

            <div class="space-y-4 text-sm text-gray-500 dark:text-gray-400">
                <p>
                    Ob Fehler, Änderungswunsch oder eine Frage zum Projekt: legen Sie es
                    als Anliegen an. Es landet direkt bei uns im System, bekommt eine
                    Nummer, und Sie sehen jederzeit, wie weit wir damit sind — ohne
                    nachfragen zu müssen.
                </p>
                <p>
                    Screenshots können Sie anhängen. Bei einem Fehler sparen sie
                    erfahrungsgemäß drei Absätze Beschreibung.
                </p>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Kunde\Resources\Anliegen\AnliegenResource::getUrl('create')"
                    icon="heroicon-o-plus"
                >
                    Anliegen anlegen
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    color="gray"
                    :href="$this->frageUrl()"
                    icon="heroicon-o-question-mark-circle"
                >
                    Nur eine Frage stellen
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Direkt erreichen</x-slot>

            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">E-Mail</dt>
                    <dd class="mt-1">
                        <a
                            href="mailto:{{ $kontakt['email'] }}"
                            class="text-primary-500 hover:underline"
                        >{{ $kontakt['email'] }}</a>
                    </dd>
                </div>

                @if (filled($kontakt['telefon']))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Telefon</dt>
                        <dd class="mt-1">
                            <a
                                href="tel:{{ preg_replace('/[^\d+]/', '', $kontakt['telefon']) }}"
                                class="text-primary-500 hover:underline"
                            >{{ $kontakt['telefon'] }}</a>
                        </dd>
                    </div>
                @endif

                @if (filled($kontakt['website']))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Website</dt>
                        <dd class="mt-1">
                            <a
                                href="{{ $kontakt['website'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary-500 hover:underline"
                            >{{ str($kontakt['website'])->after('://') }}</a>
                        </dd>
                    </div>
                @endif
            </dl>

            <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
                Was am Telefon besprochen wird, halten wir anschließend als Anliegen
                fest — damit es später nachvollziehbar bleibt.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
