{{--
    Die Messe auf der Übersicht des Kunden.

    Die Karte erscheint nur, wenn etwas ansteht — siehe istSichtbar(). Ein
    Kasten, in dem elf Monate lang "keine Termine" steht, ist derselbe Fehler
    wie ein leerer Menüpunkt: man gewöhnt sich an, ihn zu übergehen.

    Das erste Treffen bekommt die volle Karte, alle weiteren eine Zeile. Der
    Kunde fragt "wann sehen wir uns?" und meint damit das nächste; die
    übrigen beantworten nur die Anschlussfrage.
--}}
@php
    $treffen = $this->getTreffen();
    $schiff = $this->getSchiff();
    $naechstes = $treffen->first();
    $weitere = $treffen->skip(1);
@endphp

{{--
    Das Wurzelelement steht immer da: Livewire bricht ab, wenn eine
    Komponente gar nichts ausgibt ("missing root tag"). Auf der Übersicht
    taucht der leere Kasten trotzdem nie auf — dort entscheidet canView(),
    ob das Widget überhaupt gerendert wird.
--}}
<x-filament-widgets::widget>
    @if ($this->istSichtbar())
        <x-filament::section
            icon="heroicon-o-video-camera"
            heading="An Bord der {{ $schiff }}"
            description="Ihr nächstes Treffen mit uns."
        >
            @php
                $laeuft = $naechstes->laeuft();
                $abgesagt = $naechstes->istAbgesagt();
            @endphp

            <div @class([
                'rounded-xl p-5 ring-1',
                'bg-success-50 ring-success-600/20 dark:bg-success-400/10 dark:ring-success-400/30' => $laeuft,
                'bg-gray-50 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10' => ! $laeuft,
            ])>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        {{-- Der Termin steht über dem Titel und nicht darunter:
                             gefragt wird nach dem Wann, das Worum ist die
                             Anschlussfrage. --}}
                        <p @class([
                            'text-lg font-semibold',
                            'line-through text-gray-400 dark:text-gray-500' => $abgesagt,
                            'text-gray-950 dark:text-white' => ! $abgesagt,
                        ])>
                            {{ $naechstes->beginnt_am->translatedFormat('l, j. F') }}
                            um {{ $naechstes->beginnt_am->format('H:i') }} Uhr
                        </p>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $naechstes->titel }}
                            @if ($naechstes->project)
                                <span class="text-gray-400 dark:text-gray-500">· {{ $naechstes->project->name }}</span>
                            @endif
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $naechstes->dauer_minuten }} Minuten
                            @unless ($abgesagt)
                                · {{ $naechstes->beginnt_am->diffForHumans() }}
                            @endunless
                        </p>
                    </div>

                    @if ($abgesagt)
                        <x-filament::badge color="gray" icon="heroicon-o-x-circle">
                            Abgesagt
                        </x-filament::badge>
                    @elseif ($laeuft)
                        <x-filament::badge color="success" icon="heroicon-o-signal">
                            Läuft jetzt
                        </x-filament::badge>
                    @endif
                </div>

                {{-- Die Tagesordnung, wenn eine dasteht. --}}
                @if (filled($naechstes->notiz) && ! $abgesagt)
                    <p class="mt-4 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">
                        {{ $naechstes->notiz }}
                    </p>
                @endif

                @unless ($abgesagt)
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        @if (filled($naechstes->url))
                            {{-- Der Knopf, um den es geht. Hervorgehoben,
                                 solange das Treffen läuft — davor ist er da,
                                 aber ruhig. --}}
                            <x-filament::button
                                tag="a"
                                :href="$naechstes->url"
                                target="_blank"
                                :color="$laeuft ? 'success' : 'primary'"
                                icon="heroicon-o-video-camera"
                            >
                                An Bord gehen
                            </x-filament::button>
                        @endif

                        {{-- Beide Wege, weil wir den Kalender des Kunden
                             nicht kennen. Google zuerst, weil es der
                             haeufigste ist und ein Klick genuegt; die Datei
                             daneben fuer Outlook, Apple und alles andere.

                             Sie ist nicht bloss die Rueckfallebene: sie
                             traegt eine Kennung und ersetzt beim Verschieben
                             den alten Eintrag, was der Google-Link nicht
                             kann. --}}
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-calendar-days"
                            target="_blank"
                            :href="\App\Support\Kalender::googleUrl($naechstes)"
                        >
                            Google Kalender
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-arrow-down-tray"
                            :href="route('kunde.treffen.kalender', $naechstes)"
                        >
                            Kalenderdatei
                        </x-filament::button>
                    </div>
                @endunless
            </div>

            {{-- Was danach kommt, als Zeilen. --}}
            @if ($weitere->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($weitere as $eintrag)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2.5 text-sm">
                            <span @class([
                                'font-medium tabular-nums',
                                'line-through text-gray-400 dark:text-gray-500' => $eintrag->istAbgesagt(),
                                'text-gray-700 dark:text-gray-200' => ! $eintrag->istAbgesagt(),
                            ])>
                                {{ $eintrag->beginnt_am->format('d.m.Y H:i') }} Uhr
                            </span>

                            <span class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">
                                {{ $eintrag->titel }}
                            </span>

                            <a
                                href="{{ \App\Support\Kalender::googleUrl($eintrag) }}"
                                target="_blank"
                                rel="noopener"
                                class="text-xs text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Kalender
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
