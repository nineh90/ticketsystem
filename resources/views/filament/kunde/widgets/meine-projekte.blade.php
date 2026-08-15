{{--
    Die Projektkarten auf der Übersicht des Kunden.

    Aufbau jeder Karte von oben nach unten in der Reihenfolge, in der gefragt
    wird: Wie heißt es, wie weit ist es, woran arbeitet ihr gerade, wo kann
    ich es ansehen. Die Anliegen-Zahlen stehen unten — sie interessieren erst,
    wenn man selbst etwas gemeldet hat.

    Die Farbklassen für den Balken stehen ausgeschrieben da und werden nicht
    aus der Phase zusammengesetzt: Tailwind liest den Quelltext und fände
    "bg-{$farbe}-500" nicht, die Klasse fehlte dann im gebauten CSS. Dieselbe
    Falle wie im Ereignisstrom-Widget.
--}}
@php
    use App\Enums\ProjektPhase;

    $projekte = $this->getProjekte();

    $balken = [
        'gray' => 'bg-gray-400',
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'success' => 'bg-success-500',
        'primary' => 'bg-primary-500',
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-rectangle-group"
        heading="Ihre Projekte"
        description="Was wir für Sie machen — und wie weit es ist."
    >
        @if ($projekte->isEmpty())
            <div class="py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-rectangle-group"
                    class="mx-auto h-10 w-10 text-gray-400"
                />
                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                    Noch keine Projekte freigegeben
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sobald wir eines für Sie freischalten, steht es hier.
                </p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($projekte as $projekt)
                    @php
                        $phase = $projekt->phase;
                        $anteil = $this->fortschritt($projekt);
                        $adresse = $projekt->aktuelleAdresse();
                        $istLive = $projekt->zeigtLiveAdresse();
                    @endphp

                    <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">

                        {{-- Kopf: Name links, Stand rechts. --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a
                                    href="{{ \App\Filament\Kunde\Resources\Projekte\ProjektResource::getUrl('view', ['record' => $projekt]) }}"
                                    class="truncate text-base font-semibold text-gray-950 hover:text-primary-500 dark:text-white"
                                >
                                    {{ $projekt->name }}
                                </a>

                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $phase->getDescription() }}
                                </p>
                            </div>

                            <x-filament::badge :color="$phase->getColor()" :icon="$phase->getIcon()">
                                {{ $phase->getLabel() }}
                            </x-filament::badge>
                        </div>

                        {{-- Fortschritt, nur wenn Meilensteine gepflegt sind.
                             Ein Balken auf 0 % würde sonst behaupten, es sei
                             nichts geschafft — dabei wird hier nur nichts
                             nachgehalten. --}}
                        @if ($anteil !== null)
                            <div class="mt-4">
                                <div class="flex items-baseline justify-between text-xs">
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ $projekt->meilensteine_erledigt }} von {{ $projekt->meilensteine_gesamt }} Schritten
                                    </span>
                                    <span class="font-medium tabular-nums text-gray-700 dark:text-gray-200">
                                        {{ $anteil }} %
                                    </span>
                                </div>

                                <div
                                    class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
                                    role="progressbar"
                                    aria-valuenow="{{ $anteil }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div
                                        class="h-full rounded-full transition-all {{ $balken[$phase->getColor()] ?? 'bg-primary-500' }}"
                                        style="width: {{ max($anteil, 2) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif

                        {{-- Was gerade passiert, in unseren Worten. --}}
                        @if (filled($projekt->kunden_info))
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ str($projekt->kunden_info)->squish()->limit(160) }}
                            </p>
                        @endif

                        {{-- Der Rest wird nach unten gedrückt, damit die
                             Knopfreihen zweier Karten nebeneinander auf einer
                             Höhe liegen — auch wenn eine mehr Text hat. --}}
                        <div class="mt-auto pt-4">
                            <div class="flex flex-wrap items-center gap-2">
                                @if (filled($adresse))
                                    <x-filament::button
                                        tag="a"
                                        :href="$adresse"
                                        target="_blank"
                                        size="sm"
                                        :icon="$istLive ? 'heroicon-o-globe-alt' : 'heroicon-o-eye'"
                                    >
                                        {{ $istLive ? 'Seite ansehen' : 'Vorschau ansehen' }}
                                    </x-filament::button>
                                @endif

                                <x-filament::button
                                    tag="a"
                                    color="gray"
                                    size="sm"
                                    :href="\App\Filament\Kunde\Resources\Projekte\ProjektResource::getUrl('view', ['record' => $projekt])"
                                >
                                    Details
                                </x-filament::button>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>
                                    {{ $projekt->offene_anliegen }}
                                    {{ $projekt->offene_anliegen === 1 ? 'offenes Anliegen' : 'offene Anliegen' }}
                                </span>

                                {{-- Nur wenn tatsächlich etwas bei ihm liegt.
                                     Eine Zeile "0 Rückmeldungen offen" ist
                                     keine gute Nachricht, sondern Rauschen. --}}
                                @if ($projekt->am_zug > 0)
                                    <span class="font-medium text-warning-600 dark:text-warning-400">
                                        {{ $projekt->am_zug }} wartet auf Sie
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
