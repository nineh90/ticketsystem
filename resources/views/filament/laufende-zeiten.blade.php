{{--
    Die Liste der laufenden Uhren.

    Wird von zwei Stellen eingebunden — von der Zeitentabelle eines Tickets
    (als Kopf über der Tabelle) und vom Dashboard-Widget. Beide Male aus einer
    Livewire-Komponente heraus, die das Trait StopptLaufendeZeiten benutzt;
    daran hängt der Knopf rechts.

    Liegt unter views/filament/, obwohl es kein Widget ist: nur dieser Ordner
    steht als @source in resources/css/filament/admin/theme.css. Eine Zeile
    tiefer, direkt unter views/, kompiliert Tailwind die hier verwendeten
    Klassen nicht mit — die Datei sieht dann richtig aus und ist trotzdem
    ungefärbt.

    Erwartet:
      $zeiten  Collection<TimeEntry>, laufend, mit user und ticket geladen
      $poll    wie oft neu gezeichnet wird, z. B. '60s' — oder null
--}}
@php
    use App\Filament\Resources\Tickets\TicketResource;
    use App\Support\Dauer;

    $ich = auth()->user();
    $poll ??= '60s';
@endphp

<div @if ($poll) wire:poll.{{ $poll }} @endif class="divide-y divide-white/5">
    @foreach ($zeiten as $zeit)
        @php
            $eigene = $zeit->user?->is($ich) ?? false;
            $lange = $zeit->laeuftAuffaelligLange();
            $ticket = $zeit->ticket;
        @endphp

        <div @class([
            'flex items-center gap-3 py-2.5',
            // Die eigene Uhr abgesetzt: in einer Liste aus fünf Namen ist die
            // Frage "läuft bei mir noch etwas?" sonst eine Suchaufgabe.
            '-mx-2 rounded-lg bg-primary-500/5 px-2' => $eigene,
        ])>
            {{-- Der Punkt pulst. Das ist der einzige Zweck der Liste: sofort
                 sehen, dass da noch etwas läuft — nicht, dass es lief. --}}
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span @class([
                    'absolute inline-flex h-full w-full animate-ping rounded-full opacity-75',
                    'bg-danger-500' => $lange,
                    'bg-success-500' => ! $lange,
                ])></span>
                <span @class([
                    'relative inline-flex h-2.5 w-2.5 rounded-full',
                    'bg-danger-500' => $lange,
                    'bg-success-500' => ! $lange,
                ])></span>
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span class="text-sm font-medium text-gray-100">
                        {{ $eigene ? 'Du' : ($zeit->user?->name ?? 'Unbekannt') }}
                    </span>

                    @if ($ticket)
                        <a
                            href="{{ TicketResource::getUrl('view', ['record' => $ticket]) }}"
                            class="truncate text-sm text-gray-400 hover:text-primary-400"
                        >
                            {{ $ticket->kennung() }} — {{ $ticket->titel }}
                        </a>
                    @endif
                </div>

                <p class="mt-0.5 truncate text-xs text-gray-500">
                    @if ($ticket?->customer || $ticket?->project)
                        {{ collect([$ticket->customer?->name, $ticket->project?->name])->filter()->join(' · ') }}
                        <span class="text-gray-600">·</span>
                    @endif
                    seit {{ $zeit->gestartet_am->format($zeit->gestartet_am->isToday() ? 'H:i' : 'd.m. H:i') }} Uhr
                </p>
            </div>

            <span @class([
                'shrink-0 rounded-md px-2 py-1 text-xs font-medium tabular-nums ring-1',
                'bg-danger-500/10 text-danger-400 ring-danger-500/20' => $lange,
                'bg-success-500/10 text-success-400 ring-success-500/20' => ! $lange,
            ])>
                {{ Dauer::alsStunden($zeit->bisherigeMinuten()) }}
            </span>

            {{-- Die eigene Uhr darf jeder stoppen, fremde nur Administratoren
                 — dieselbe Regel wie beim Bearbeiten, siehe TimeEntryPolicy. --}}
            @can('update', $zeit)
                <button
                    type="button"
                    wire:click="zeitStoppen({{ $zeit->getKey() }})"
                    wire:loading.attr="disabled"
                    wire:target="zeitStoppen({{ $zeit->getKey() }})"
                    class="flex shrink-0 items-center gap-1 rounded-md bg-gray-800 px-2 py-1 text-xs text-gray-300 ring-1 ring-gray-700 transition hover:bg-gray-700 hover:text-white disabled:opacity-50"
                >
                    <x-filament::icon icon="heroicon-m-stop" class="h-3.5 w-3.5" />
                    Stoppen
                </button>
            @endcan
        </div>
    @endforeach
</div>
