{{--
    Kanban-Brett.

    Das Ziehen ist mit reinem Alpine und der HTML5-Drag-and-Drop-API gebaut,
    ohne zusätzliche Bibliothek: eine solche käme entweder von einem CDN, was
    unsere CSP verbietet, oder als weiteres npm-Paket, das für sieben Spalten
    nicht lohnt.
--}}
<x-filament-panels::page>
    @php
        $stadien = $this->getStadien();
        $tickets = $this->getTicketsNachStadium();
        $projekte = $this->getProjekte();
    @endphp

    {{-- Projektfilter --}}
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="{{ \App\Filament\Pages\Kanban::getUrl() }}"
            @class([
                'fi-badge fi-size-md rounded-full px-3 py-1 text-sm',
                'bg-primary-500 text-gray-950' => ! $this->projektId,
                'bg-gray-800 text-gray-300 hover:bg-gray-700' => $this->projektId,
            ])
        >
            Alle Projekte
        </a>

        @foreach ($projekte as $projekt)
            <a
                href="{{ \App\Filament\Pages\Kanban::getUrl() }}?projekt={{ $projekt->id }}"
                @class([
                    'fi-badge fi-size-md rounded-full px-3 py-1 text-sm',
                    'bg-primary-500 text-gray-950' => $this->projektId === $projekt->id,
                    'bg-gray-800 text-gray-300 hover:bg-gray-700' => $this->projektId !== $projekt->id,
                ])
            >
                {{ $projekt->customer->kuerzel }} — {{ $projekt->name }}
            </a>
        @endforeach
    </div>

    {{-- Das Brett scrollt waagerecht in sich selbst, damit die Seite es nicht tut. --}}
    <div
        class="flex gap-4 overflow-x-auto pb-4"
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
        }"
    >
        @foreach ($stadien as $stadium)
            @php $spalte = $tickets->get($stadium->id) ?? collect(); @endphp

            <div
                class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-900 ring-1 ring-white/10"
                x-on:dragover.prevent
                x-on:drop.prevent="ablegen({{ $stadium->id }}, $el)"
            >
                <div class="flex items-center justify-between gap-2 border-b border-white/10 px-3 py-2">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <span
                            class="inline-block h-2.5 w-2.5 rounded-full"
                            style="background-color: {{ $stadium->farbe }}"
                        ></span>
                        {{ $stadium->name }}
                    </span>
                    <span class="rounded-full bg-gray-800 px-2 py-0.5 text-xs text-gray-400">
                        {{ $spalte->count() }}
                    </span>
                </div>

                <div class="flex min-h-24 flex-col gap-2 p-2">
                    @forelse ($spalte as $ticket)
                        <a
                            href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ticket]) }}"
                            data-ticket="{{ $ticket->id }}"
                            draggable="true"
                            x-on:dragstart="aufnehmen({{ $ticket->id }})"
                            class="block cursor-grab rounded-lg bg-gray-950 p-3 ring-1 ring-white/10 transition hover:-translate-y-0.5 hover:ring-primary-500/40 active:cursor-grabbing"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="rounded bg-gray-800 px-1.5 py-0.5 text-xs text-gray-400">
                                    {{ $ticket->kennung() }}
                                </span>

                                @if ($ticket->faellig_am)
                                    <span @class([
                                        'text-xs',
                                        'text-danger-400' => $ticket->faellig_am->isPast() && ! $ticket->erledigt_at,
                                        'text-gray-500' => ! ($ticket->faellig_am->isPast() && ! $ticket->erledigt_at),
                                    ])>
                                        {{ $ticket->faellig_am->format('d.m.') }}
                                    </span>
                                @endif
                            </div>

                            <p class="mt-2 text-sm leading-snug">{{ $ticket->titel }}</p>

                            <p class="mt-1 text-xs text-gray-500">
                                {{ $ticket->customer->name }} · {{ $ticket->project->name }}
                            </p>

                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-danger-500/15 text-danger-400' => $ticket->prioritaet === \App\Enums\Prioritaet::Dringend,
                                    'bg-warning-500/15 text-warning-400' => $ticket->prioritaet === \App\Enums\Prioritaet::Hoch,
                                    'bg-gray-800 text-gray-400' => in_array($ticket->prioritaet, [\App\Enums\Prioritaet::Normal, \App\Enums\Prioritaet::Niedrig], true),
                                ])>
                                    {{ $ticket->prioritaet->getLabel() }}
                                </span>

                                <span class="truncate text-xs text-gray-500">
                                    {{ $ticket->zustaendig?->name ?? 'niemand' }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <p class="px-1 py-4 text-center text-xs text-gray-600">leer</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
