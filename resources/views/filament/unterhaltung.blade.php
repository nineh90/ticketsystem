{{--
    Ein Verlauf samt Eingabefeld.

    Wird von beiden Panels eingebunden — innen von der Seite "Nachrichten",
    außen vom gleichnamigen Punkt im Kundenbereich. Beide Male aus einer
    Livewire-Komponente heraus, die `entwurf` und `senden()` hat; daran hängt
    das Feld unten.

    Liegt unter views/filament/, obwohl es keine Seite ist: nur dieser Ordner
    steht als @source in resources/css/filament/admin/theme.css. Eine Ebene
    höher kompiliert Tailwind die Klassen hier nicht mit, und der Verlauf
    steht ungefärbt da. Dieselbe Falle wie bei den laufenden Zeiten.

    Erwartet:
      $unterhaltung  Unterhaltung mit geladenen nachrichten.absender
      $ich           der angemeldete Nutzer
      $poll          wie oft neu gezeichnet wird, z. B. '30s' — oder null
--}}
@php
    $poll ??= '30s';
    $nachrichten = $unterhaltung->nachrichten;
@endphp

<div class="flex h-[32rem] flex-col">
    {{-- Der Verlauf. Neu geladen im Takt, damit eine Antwort ankommt, ohne
         dass jemand die Seite neu lädt — dieselbe Überlegung wie bei der
         Glocke, nur ohne Klick dazwischen. --}}
    <div
        @if ($poll) wire:poll.{{ $poll }} @endif
        class="flex-1 space-y-3 overflow-y-auto pr-1"
        x-data
        x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
    >
        @forelse ($nachrichten as $nachricht)
            @php
                $vonMir = $nachricht->absender?->is($ich) ?? false;
            @endphp

            <div @class(['flex', 'justify-end' => $vonMir])>
                <div @class([
                    'max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm',
                    // Eigene rechts und eingefärbt, fremde links und grau.
                    // Ohne den Unterschied liest sich ein Verlauf wie ein
                    // Protokoll, in dem man jede Zeile mit dem Namen davor
                    // abgleichen muss.
                    'bg-primary-500/15 text-gray-100 ring-1 ring-primary-500/25' => $vonMir,
                    'bg-white/5 text-gray-200 ring-1 ring-white/10' => ! $vonMir,
                ])>
                    @unless ($vonMir)
                        <p class="mb-1 text-xs font-medium text-primary-400">
                            {{ $nachricht->absender?->name ?? 'Gelöschter Zugang' }}
                        </p>
                    @endunless

                    {{-- Reiner Text, deshalb ohne {!! !!}: was hier ankommt,
                         hat ein Kunde getippt. Zeilenumbrüche bleiben über
                         whitespace-pre-line erhalten, ohne dass dafür Markup
                         durchgelassen werden müsste. --}}
                    <p class="whitespace-pre-line break-words">{{ $nachricht->text }}</p>

                    <p @class([
                        'mt-1 text-right text-[0.6875rem] tabular-nums',
                        'text-primary-300/70' => $vonMir,
                        'text-gray-500' => ! $vonMir,
                    ])>
                        {{ $nachricht->created_at->format($nachricht->created_at->isToday() ? 'H:i' : 'd.m. H:i') }}
                    </p>
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-sm text-gray-500">
                Noch nichts geschrieben. Der erste Satz steht unten.
            </p>
        @endforelse
    </div>

    {{-- Absenden mit Enter, neue Zeile mit Umschalt+Enter. Ohne das tippt man
         beim ersten Absatz versehentlich ab — und mit Enter allein als
         einziger Möglichkeit könnte man keinen Absatz setzen. --}}
    <form wire:submit="senden" class="mt-4 flex items-end gap-2 border-t border-white/5 pt-4">
        <textarea
            wire:model="entwurf"
            x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.senden() }"
            rows="2"
            placeholder="Nachricht schreiben …"
            class="flex-1 resize-none rounded-lg border-none bg-white/5 px-3 py-2 text-sm text-gray-100 ring-1 ring-white/10 placeholder:text-gray-500 focus:ring-2 focus:ring-primary-500"
        ></textarea>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="senden"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-500 disabled:opacity-50"
        >
            <x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />
            Senden
        </button>
    </form>
</div>
