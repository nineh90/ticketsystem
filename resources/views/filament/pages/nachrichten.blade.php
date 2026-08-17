{{--
    Links die Fäden, rechts der offene.

    Die Aufteilung ist der Grund, warum das hier eine eigene Seite ist und
    keine Ressource: eine Liste, die beim Anklicken die Seite wechselt,
    zwingt bei jedem Blick in einen anderen Verlauf zu zwei Ladevorgängen —
    und nach dem dritten Mal schreibt man wieder eine Mail.
--}}
@php
    $ich = auth()->user();
    $aktuelle = $this->getAktuelle();
    $kunden = $this->getKundenfaeden();
    $intern = $this->getInternenFaeden();
@endphp

<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Die Liste --}}
        <div class="xl:col-span-1">
            <x-filament::section>
                @if ($kunden->isEmpty() && $intern->isEmpty())
                    <div class="py-6 text-center">
                        <x-filament::icon
                            icon="heroicon-o-chat-bubble-left-right"
                            class="mx-auto h-8 w-8 text-gray-600"
                        />
                        <p class="mt-3 text-sm font-medium text-gray-300">Noch keine Unterhaltung</p>
                        <p class="mt-1 text-sm text-gray-500">
                            Oben rechts eine mit einem Kunden oder einem Kollegen beginnen.
                            Sie ist an kein Ticket gebunden.
                        </p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach ([['Kunden', $kunden, 'heroicon-m-building-office-2'], ['Intern', $intern, 'heroicon-m-user']] as [$ueberschrift, $faeden, $symbol])
                            @if ($faeden->isNotEmpty())
                                <div>
                                    <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <x-filament::icon :icon="$symbol" class="h-3.5 w-3.5" />
                                        {{ $ueberschrift }}
                                    </p>

                                    <div class="space-y-1">
                                        @foreach ($faeden as $faden)
                                            @php
                                                $offen = $aktuelle?->is($faden) ?? false;
                                                $ungelesen = $faden->ungeleseneFuer($ich);
                                            @endphp

                                            <button
                                                type="button"
                                                wire:click="oeffnen({{ $faden->getKey() }})"
                                                @class([
                                                    'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left transition',
                                                    'bg-primary-500/10 ring-1 ring-primary-500/25' => $offen,
                                                    'hover:bg-white/5' => ! $offen,
                                                ])
                                            >
                                                <div class="min-w-0 flex-1">
                                                    <p @class([
                                                        'truncate text-sm',
                                                        'font-semibold text-white' => $ungelesen > 0,
                                                        'text-gray-200' => $ungelesen === 0,
                                                    ])>
                                                        {{ $faden->titelFuer($ich) }}
                                                    </p>

                                                    @if ($faden->letzte_nachricht_am)
                                                        <p class="mt-0.5 text-xs text-gray-500">
                                                            {{ $faden->letzte_nachricht_am->diffForHumans(short: true) }}
                                                        </p>
                                                    @endif
                                                </div>

                                                {{-- Die Zahl nur, wenn sie etwas sagt. Eine Null neben
                                                     jedem Namen macht die Liste unruhig und die eine
                                                     Zeile, auf die es ankommt, unauffällig. --}}
                                                @if ($ungelesen > 0)
                                                    <span class="shrink-0 rounded-full bg-primary-500 px-2 py-0.5 text-xs font-semibold text-white tabular-nums">
                                                        {{ $ungelesen }}
                                                    </span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Der offene Faden --}}
        <div class="xl:col-span-2">
            <x-filament::section
                :heading="$aktuelle?->titelFuer($ich) ?? 'Kein Verlauf geöffnet'"
                :description="$aktuelle === null
                    ? null
                    : ($aktuelle->istIntern()
                        ? 'Nur ihr beide lest mit — auch kein Administrator.'
                        : 'Alle Zugänge dieses Kunden und alle Zuständigen bei uns lesen mit.')"
                {{-- Ohne offenen Verlauf ein neutrales Symbol: die Ternäre
                     unten fiele sonst auf "Kunde" zurück, und über einer
                     leeren Fläche stünde das Zeichen für etwas, das gar
                     nicht ausgewählt ist. --}}
                :icon="match (true) {
                    $aktuelle === null => 'heroicon-o-chat-bubble-left-right',
                    $aktuelle->istIntern() => 'heroicon-o-user',
                    default => 'heroicon-o-building-office-2',
                }"
            >
                @if ($aktuelle === null)
                    <p class="py-8 text-center text-sm text-gray-500">
                        Links einen Verlauf auswählen oder oben rechts einen neuen beginnen.
                    </p>
                @else
                    @include('filament.unterhaltung', ['unterhaltung' => $aktuelle, 'ich' => $ich])
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
