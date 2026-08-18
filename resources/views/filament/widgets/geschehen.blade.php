{{--
    Der Ereignisstrom.

    Bewusst eine Zeitleiste und keine Tabelle: die Einträge sind ungleich lang
    — ein Statuswechsel ist eine Zeile, ein Kommentar können zehn sein. In
    Tabellenspalten gepresst wäre entweder der Kommentar abgeschnitten oder
    die Zeilenhöhe der ganzen Liste ruiniert.
--}}
@php
    use App\Support\Ereignis;

    $ereignisse = $this->getEreignisse();
    $seit = $this->getGesehenSeit();
    $gruppen = $ereignisse->groupBy(fn (Ereignis $e) => $e->tag());

    // Die Farbklassen stehen ausgeschrieben da, statt aus $e->farbe()
    // zusammengesetzt zu werden: Tailwind liest den Quelltext und findet
    // "bg-{$farbe}-500/10" nicht — die Klasse fehlte dann im gebauten CSS.
    $stile = [
        Ereignis::KOMMENTAR => 'bg-primary-500/10 text-primary-400 ring-primary-500/20',
        Ereignis::ANGELEGT => 'bg-success-500/10 text-success-400 ring-success-500/20',
        Ereignis::ZEIT => 'bg-info-500/10 text-info-400 ring-info-500/20',
        Ereignis::ANHANG => 'bg-warning-500/10 text-warning-400 ring-warning-500/20',
        Ereignis::AENDERUNG => 'bg-gray-500/10 text-gray-400 ring-gray-500/20',
        Ereignis::DOKUMENT => 'bg-success-500/10 text-success-400 ring-success-500/20',
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-bolt"
        :heading="'Was passiert ist'"
        :description="$this->neu > 0
            ? $this->neu . ' ' . ($this->neu === 1 ? 'Ereignis' : 'Ereignisse') . ' seit deinem letzten Besuch'
            : 'Kommentare, Änderungen, Zeiten und Anhänge aus allen Tickets, die du siehst — dazu Antworten auf Angebote'"
    >
        {{-- Alle 60 Sekunden nachladen: das Dashboard bleibt den Tag über
             offen und soll dann auch etwas zeigen, ohne dass jemand F5 drückt. --}}
        <div wire:poll.60s class="space-y-4">
            {{-- Filter. Beide Reihen haben einen Eintrag "Alles"; ohne die
                 Beschriftung davor sähen sie aus wie eine einzige Leiste, in
                 der zweimal dasselbe steht. --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="mr-1 text-xs text-gray-500">Wessen:</span>

                    @foreach ($this->umfaenge() as $wert => $beschriftung)
                        <button
                            type="button"
                            wire:click="setzeUmfang('{{ $wert }}')"
                            @class([
                                'rounded-full px-3 py-1 text-xs transition',
                                'bg-primary-500 font-medium text-gray-950' => $this->umfang === $wert,
                                'bg-gray-800 text-gray-300 hover:bg-gray-700' => $this->umfang !== $wert,
                            ])
                        >
                            {{ $beschriftung }}
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="mr-1 text-xs text-gray-500">Was:</span>

                    @foreach ($this->typen() as $wert => $beschriftung)
                        <button
                            type="button"
                            wire:click="setzeTyp('{{ $wert }}')"
                            @class([
                                'rounded-full px-3 py-1 text-xs ring-1 transition',
                                'bg-gray-700/60 text-gray-100 ring-gray-600' => $this->typ === $wert,
                                'text-gray-400 ring-gray-700 hover:text-gray-200' => $this->typ !== $wert,
                            ])
                        >
                            {{ $beschriftung }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($ereignisse->isEmpty())
                <div class="py-8 text-center">
                    <x-filament::icon
                        icon="heroicon-o-inbox"
                        class="mx-auto h-8 w-8 text-gray-500"
                    />
                    <p class="mt-2 text-sm font-medium text-gray-300">
                        @if ($this->ohneZuordnung())
                            Keine Zuordnung
                        @elseif ($this->gefiltert())
                            Nichts in dieser Auswahl
                        @else
                            Noch nichts passiert
                        @endif
                    </p>
                    <p class="mx-auto mt-1 max-w-md text-xs text-gray-500">
                        @if ($this->ohneZuordnung())
                            Dir ist noch kein Kunde und kein Projekt zugeordnet — deshalb ist hier nichts zu sehen.
                            Ein Administrator ordnet dich unter <strong>Verwaltung → Nutzer</strong> zu.
                        @elseif ($this->gefiltert())
                            {{-- Sonst suchte man den Fehler im System statt in
                                 den zwei Knöpfen direkt darüber. --}}
                            Passiert ist durchaus etwas — nur nichts, worauf die gewählten Filter passen.
                        @else
                            Sobald jemand kommentiert, ein Ticket ändert, Zeit erfasst oder eine Datei
                            anhängt, steht es hier.
                        @endif
                    </p>
                </div>
            @else
                {{-- Der Strom scrollt in sich selbst. Ohne die Höhenbremse
                     wäre die Karte drei Bildschirme lang, und die Ticketliste
                     daneben stünde in einem Meer von Weißraum. --}}
                <div class="max-h-[32rem] space-y-5 overflow-y-auto pr-1">
                    @foreach ($gruppen as $tag => $eintraege)
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $tag }}
                            </h3>

                            <div class="divide-y divide-white/5">
                                @foreach ($eintraege as $ereignis)
                                    @php $ist_neu = $ereignis->istNeu($seit); @endphp

                                    <div @class([
                                        'flex gap-3 py-3',
                                        '-mx-2 rounded-lg bg-primary-500/5 px-2' => $ist_neu,
                                    ])>
                                        <div @class([
                                            'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1',
                                            $stile[$ereignis->typ] ?? $stile[Ereignis::AENDERUNG],
                                        ])>
                                            <x-filament::icon :icon="$ereignis->icon()" class="h-4 w-4" />
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-baseline gap-x-2">
                                                <span class="text-sm font-medium text-gray-200">
                                                    {{ $ereignis->urheber() }}
                                                </span>
                                                <span class="text-sm text-gray-400">{{ $ereignis->was }}</span>

                                                @if ($ereignis->intern)
                                                    <span class="rounded bg-gray-800 px-1.5 py-0.5 text-[0.65rem] text-gray-400">
                                                        intern
                                                    </span>
                                                @endif

                                                @if ($ist_neu)
                                                    <span class="rounded bg-primary-500/20 px-1.5 py-0.5 text-[0.65rem] font-medium text-primary-400">
                                                        neu
                                                    </span>
                                                @endif

                                                <span
                                                    class="ml-auto shrink-0 text-xs text-gray-500"
                                                    title="{{ $ereignis->zeitpunkt->format('d.m.Y H:i') }}"
                                                >
                                                    {{ $ereignis->zeitpunkt->format('H:i') }}
                                                </span>
                                            </div>

                                            @if ($ereignis->ticket)
                                                <a
                                                    href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ereignis->ticket]) }}"
                                                    class="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm hover:underline"
                                                >
                                                    <span class="rounded bg-gray-800 px-1.5 py-0.5 font-mono text-xs text-gray-300">
                                                        {{ $ereignis->ticket->kennung() }}
                                                    </span>
                                                    <span class="text-gray-200">{{ $ereignis->ticket->titel }}</span>
                                                    <span class="text-xs text-gray-500">
                                                        {{ $ereignis->ticket->customer?->name }}
                                                        @if ($ereignis->ticket->project)
                                                            · {{ $ereignis->ticket->project->name }}
                                                        @endif
                                                    </span>
                                                </a>
                                            @endif

                                            {{-- Einträge ohne Ticket brauchen trotzdem einen Bezug:
                                                 eine Angebotszusage ohne Kundennamen wäre eine
                                                 Meldung, zu der man erst suchen muss. --}}
                                            @if (! $ereignis->ticket && $ereignis->kontext)
                                                @if ($ereignis->kontextUrl)
                                                    <a
                                                        href="{{ $ereignis->kontextUrl }}"
                                                        class="mt-1 block text-sm text-gray-200 hover:underline"
                                                    >
                                                        {{ $ereignis->kontext }}
                                                    </a>
                                                @else
                                                    <p class="mt-1 text-sm text-gray-200">{{ $ereignis->kontext }}</p>
                                                @endif
                                            @endif

                                            @foreach ($ereignis->zeilen as $zeile)
                                                <p class="mt-1 text-xs text-gray-400">{{ $zeile }}</p>
                                            @endforeach

                                            @if ($ereignis->zitat)
                                                <p class="mt-2 whitespace-pre-line rounded-lg bg-gray-900/70 px-3 py-2 text-sm text-gray-300 ring-1 ring-white/5">{{ $ereignis->zitat }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if ($this->hatMehr($ereignisse->count()))
                        {{-- Im Scrollbereich, nicht darunter: der Knopf gehört
                             ans Ende der Liste, nicht ans Ende der Karte. --}}
                        <div class="pt-1 text-center">
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="mehrAnzeigen"
                                wire:loading.attr="disabled"
                            >
                                Mehr anzeigen
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
