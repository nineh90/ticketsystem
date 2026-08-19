{{--
    Die Wochenvorschau auf der Brücke.

    Nach Tagen gruppiert und nicht als eine lange Liste: "Donnerstag" ist die
    Einheit, in der man plant. Der Tageskopf steht links und bleibt schmal,
    damit die Termine daneben eine gemeinsame Kante haben — sonst sucht das
    Auge bei jedem Tag neu.

    Die Farbklassen stehen ausgeschrieben da und werden nicht aus der Art
    zusammengesetzt: Tailwind liest den Quelltext und fände "text-{$farbe}-500"
    nicht, die Klasse fehlte dann im gebauten CSS. Dieselbe Falle wie im
    Ereignisstrom und in den Projektkarten.
--}}
@php
    $tage = $this->getTage();

    $punkt = [
        'primary' => 'bg-primary-500',
        'info'    => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'gray'    => 'bg-gray-400',
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-calendar-days"
        heading="Diese Woche"
        description="Treffen, Meilensteine, Fristen und fällige Tickets der nächsten sieben Tage."
    >
        @if ($tage->isEmpty())
            {{-- Ausdrücklich eine Antwort und keine leere Fläche: "nichts
                 liegt an" ist eine Information, eine leere Karte ist ein
                 Zweifel daran, ob sie geladen hat. --}}
            <div class="py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="mx-auto h-8 w-8 text-gray-400"
                />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Diese Woche steht nichts an.
                </p>
            </div>
        @else
            <div class="flex flex-col gap-5">
                @foreach ($tage as $datum => $termine)
                    @php
                        $tag = \Illuminate\Support\Carbon::parse($datum);
                        $heute = $tag->isToday();
                    @endphp

                    <div class="flex flex-col gap-2 sm:flex-row sm:gap-5">
                        {{-- Der Tageskopf. Feste Breite, damit die Termine
                             aller Tage auf einer Kante stehen. --}}
                        <div class="sm:w-28 sm:shrink-0 sm:pt-1">
                            <p @class([
                                'text-sm font-semibold',
                                'text-primary-600 dark:text-primary-400' => $heute,
                                'text-gray-950 dark:text-white' => ! $heute,
                            ])>
                                {{ $heute ? 'Heute' : $tag->translatedFormat('D, j.n.') }}
                            </p>

                            @if ($heute)
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $tag->translatedFormat('D, j.n.') }}
                                </p>
                            @endif
                        </div>

                        <ul class="min-w-0 flex-1 flex flex-col gap-2">
                            @foreach ($termine as $termin)
                                <li class="flex items-start gap-3">
                                    {{-- Ein Punkt statt eines Symbols je
                                         Zeile: vier Symbole untereinander
                                         lesen sich wie eine Werkzeugleiste,
                                         der Punkt bleibt eine Markierung. --}}
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $punkt[$termin->farbe()] ?? 'bg-gray-400' }}"></span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-baseline gap-x-2">
                                            @unless ($termin->ganztaegig)
                                                <span class="text-sm font-medium tabular-nums text-gray-950 dark:text-white">
                                                    {{ $termin->zeitpunkt->format('H:i') }}
                                                </span>
                                            @endunless

                                            @if ($termin->url)
                                                <a
                                                    href="{{ $termin->url }}"
                                                    class="truncate text-sm text-gray-700 hover:text-primary-500 dark:text-gray-200"
                                                >{{ $termin->titel }}</a>
                                            @else
                                                <span class="truncate text-sm text-gray-700 dark:text-gray-200">
                                                    {{ $termin->titel }}
                                                </span>
                                            @endif
                                        </div>

                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $termin->bezeichnung() }}
                                            @if ($termin->kunde)
                                                · {{ $termin->kunde }}
                                            @endif
                                            @if ($termin->zusatz)
                                                · {{ $termin->zusatz }}
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
