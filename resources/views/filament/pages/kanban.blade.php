{{--
    Kanban-Brett.

    Das Ziehen ist mit reinem Alpine und der HTML5-Drag-and-Drop-API gebaut,
    ohne zusätzliche Bibliothek: eine solche käme entweder von einem CDN, was
    unsere CSP verbietet, oder als weiteres npm-Paket, das für sieben Spalten
    nicht lohnt.

    Der Aufbau folgt einer Regel, die vorher fehlte: das Brett ist so hoch wie
    der Bildschirm und keinen Pixel höher. Vorher wuchs es mit der längsten
    Spalte — bei sechsundzwanzig Karten auf gut dreitausend Pixel, und die
    waagerechte Bildlaufleiste saß ganz unten an deren Ende. Wer nach rechts
    wollte, musste erst durch die ganze Spalte nach unten. Jetzt scrollt jede
    Spalte für sich, und die Leiste steht immer da, wo man sie sucht.
--}}
<x-filament-panels::page>
    @php
        $spalten = $this->getSpalten();
        $kunden = $this->getKunden();
        $projekte = $this->getProjekte();
    @endphp

    @if (\App\Support\Sichtbarkeit::ohneProjekte())
        {{-- Ohne Zuordnung ist das Brett leer, und zwar ohne erkennbaren
             Grund. Der Hinweis steht deshalb über den Spalten, nicht nur in
             deren Leerzustand. --}}
        <div class="rounded-xl bg-warning-500/10 p-4 text-sm text-warning-400 ring-1 ring-warning-500/30">
            Dir ist noch kein Kunde und kein Projekt zugeordnet — deshalb ist das Brett leer.
            Ein Administrator ordnet dich unter <strong>Verwaltung → Nutzer</strong> zu.
        </div>
    @endif

    {{--
        Die Vorauswahl in einer Zeile.

        Vorher stand hier jedes Projekt als eigenes Abzeichen — elf Stück in
        zwei Zeilen, und mit jedem neuen Projekt eines mehr. Zwei Auswahlfelder
        kosten einen Klick mehr und dafür immer dieselbe Höhe.
    --}}
    <div class="flex flex-wrap items-end gap-3">
        {{--
            Filaments eigene Feldkomponenten statt selbst gesetzter Farben.
            Hier standen einmal eigene Klassen — die trafen den Ton der
            übrigen Eingabefelder nicht, und sie hätten ihn beim nächsten
            Theme-Wechsel erst recht verfehlt. Aussehen ist nichts, was diese
            Seite selbst entscheiden sollte.
        --}}
        <label class="flex flex-col gap-1">
            <span class="text-xs font-medium text-gray-400">Kunde</span>
            <x-filament::input.wrapper class="w-52">
                <x-filament::input.select wire:model.live="kundeId">
                    <option value="">Alle Kunden</option>
                    @foreach ($kunden as $kunde)
                        <option value="{{ $kunde->id }}">{{ $kunde->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs font-medium text-gray-400">Projekt</span>
            <x-filament::input.wrapper class="w-64">
                <x-filament::input.select wire:model.live="projektId">
                    <option value="">Alle Projekte</option>
                    @foreach ($projekte as $projekt)
                        <option value="{{ $projekt->id }}">
                            {{ $projekt->customer->kuerzel }} — {{ $projekt->name }}
                        </option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="flex cursor-pointer items-center gap-2 py-2 text-sm text-gray-300">
            <x-filament::input.checkbox wire:model.live="nurMeine" />
            Nur meine
        </label>

        @if ($this->hatVorauswahl())
            <button
                type="button"
                wire:click="zuruecksetzen"
                class="ms-2 py-2 text-sm text-gray-400 underline-offset-4 hover:text-gray-200 hover:underline"
            >
                Zurücksetzen
            </button>
        @endif
    </div>

    {{--
        Das Brett. Feste Höhe statt mitwachsender: 100vh minus dem, was Kopf,
        Titel und Filterzeile darüber belegen. Die Untergrenze verhindert, dass
        auf einem kurzen Fenster Spalten entstehen, in die keine Karte passt.
    --}}
    <div
        class="flex h-[calc(100vh-21rem)] min-h-96 gap-4 overflow-x-auto pb-2"
        x-data="{
            gezogen: null,
            aufnehmen(id) { this.gezogen = id },
            ablegen(stadiumId, el) {
                if (! this.gezogen) return
                const reihenfolge = [...el.querySelectorAll('[data-ticket]')]
                    .map(k => Number(k.dataset.ticket))
                $wire.verschieben(this.gezogen, stadiumId, reihenfolge)
                this.gezogen = null
            },
            /*
                Beim Ziehen an den Rand wandert das Brett mit. Ohne das kommt
                man mit einer Karte in der Hand nicht in eine Spalte, die
                gerade nicht zu sehen ist — die Bildlaufleiste lässt sich
                nicht bedienen, solange die Maustaste gedrückt ist.
            */
            randScrollen(event) {
                const rand = 120
                const abstand = 18
                const kasten = this.$el.getBoundingClientRect()

                if (event.clientX < kasten.left + rand) {
                    this.$el.scrollLeft -= abstand
                } else if (event.clientX > kasten.right - rand) {
                    this.$el.scrollLeft += abstand
                }
            },
        }"
        x-on:dragover.prevent="randScrollen($event)"
        x-on:kanban-gefiltert.window="$el.scrollLeft = 0"
    >
        @foreach ($spalten as $spalte)
            <div
                class="flex h-full w-72 shrink-0 flex-col rounded-xl bg-gray-900 ring-1 ring-white/10"
                x-on:dragover.prevent
                x-on:drop.prevent="ablegen({{ $spalte->stadium->id }}, $el)"
            >
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-white/10 px-3 py-2">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <span
                            class="inline-block h-2.5 w-2.5 rounded-full"
                            style="background-color: {{ $spalte->stadium->farbe }}"
                        ></span>
                        {{ $spalte->stadium->name }}
                    </span>
                    <span class="rounded-full bg-gray-800 px-2 py-0.5 text-xs text-gray-400">
                        {{ $spalte->gesamt }}
                    </span>
                </div>

                {{-- Nur dieser Teil scrollt, der Spaltenkopf bleibt stehen. --}}
                <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto p-2">
                    @forelse ($spalte->karten as $ticket)
                        <a
                            href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ticket]) }}"
                            data-ticket="{{ $ticket->id }}"
                            draggable="true"
                            x-on:dragstart="aufnehmen({{ $ticket->id }})"
                            class="block shrink-0 cursor-grab rounded-lg bg-gray-950 p-2.5 ring-1 ring-white/10 transition hover:-translate-y-0.5 hover:ring-primary-500/40 active:cursor-grabbing"
                        >
                            {{--
                                Die Karte ist auf drei Zeilen eingedampft.
                                Vorher waren es vier: Kennung, Titel, Kunde und
                                Projekt, dann Priorität und Zuständigkeit. Auf
                                einem Brett, das man überfliegt, zählt jede
                                Zeile doppelt — man sieht dadurch eine Karte
                                mehr je Spalte, ohne dass etwas fehlt.
                            --}}
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 rounded bg-gray-800 px-1.5 py-0.5 text-xs text-gray-400">
                                    {{ $ticket->kennung() }}
                                </span>

                                <div class="flex items-center gap-1.5">
                                    {{-- Nur was auffallen soll, fällt auf: bei
                                         normaler und niedriger Priorität steht
                                         hier nichts. Ein "Normal" an jeder
                                         Karte ist kein Hinweis, sondern
                                         Grundrauschen, in dem das "Dringend"
                                         daneben untergeht. --}}
                                    @if (in_array($ticket->prioritaet, [\App\Enums\Prioritaet::Dringend, \App\Enums\Prioritaet::Hoch], true))
                                        <span @class([
                                            'rounded-full px-1.5 py-0.5 text-[0.65rem] font-medium',
                                            'bg-danger-500/15 text-danger-400' => $ticket->prioritaet === \App\Enums\Prioritaet::Dringend,
                                            'bg-warning-500/15 text-warning-400' => $ticket->prioritaet === \App\Enums\Prioritaet::Hoch,
                                        ])>
                                            {{ $ticket->prioritaet->getLabel() }}
                                        </span>
                                    @endif

                                    @if ($ticket->faellig_am)
                                        <span @class([
                                            'shrink-0 text-xs',
                                            'text-danger-400' => $ticket->faellig_am->isPast() && ! $ticket->erledigt_at,
                                            'text-gray-500' => ! ($ticket->faellig_am->isPast() && ! $ticket->erledigt_at),
                                        ])>
                                            {{ $ticket->faellig_am->format('d.m.') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <p class="mt-1.5 text-sm leading-snug">{{ $ticket->titel }}</p>

                            <div class="mt-1.5 flex items-baseline justify-between gap-2 text-xs text-gray-500">
                                <span class="truncate">
                                    {{ $ticket->customer->kuerzel }} · {{ $ticket->project->name }}
                                </span>
                                <span class="shrink-0">{{ $ticket->zustaendig?->name ?? 'niemand' }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="px-1 py-4 text-center text-xs text-gray-600">leer</p>
                    @endforelse

                    @if ($spalte->verborgen > 0)
                        {{-- Kein stilles Abschneiden: die Zahl oben nennt alle,
                             hier steht, wie viele davon nicht auf dem Brett
                             sind, und der Weg zu ihnen. --}}
                        <a
                            href="{{ $this->listeFuerStadium($spalte->stadium) }}"
                            class="shrink-0 rounded-lg border border-dashed border-white/10 px-2 py-3 text-center text-xs text-gray-500 transition hover:border-primary-500/40 hover:text-primary-400"
                        >
                            … und {{ $spalte->verborgen }} weitere
                            <span class="block text-[0.65rem]">in der Ticketliste ansehen</span>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
