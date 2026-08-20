{{--
    Wer wann was gebucht hat.

    Wird vom Fenster hinter beiden Zeit-Kacheln eingebunden — auf der Brücke
    für den Betriebstag, auf der Wache für die eigene Woche.

    Erwartet:
      $zeiten    Collection<TimeEntry>, neueste zuerst, mit user und ticket geladen
      $mitNamen  ob in jeder Zeile stehen soll, wer sie gebucht hat
--}}
@php
    use App\Filament\Resources\Tickets\TicketResource;
    use App\Support\Dauer;

    $ich = auth()->user();
    $mitNamen ??= false;

    $nachTagen = $zeiten->groupBy(fn ($zeit) => $zeit->gestartet_am->toDateString());

    // Der Tageskopf nur, wenn es mehrere Tage sind. Über einer Liste, die
    // ohnehin nur den heutigen Tag zeigt, wäre er eine Zeile, die nichts sagt.
    $mehrereTage = $nachTagen->count() > 1;

    // Dieselbe Summe wie auf der Kachel: die Spalte minuten, nicht die
    // bisherige Laufzeit. Eine laufende Uhr steht dort noch auf 0 — sie wird
    // unten getrennt genannt, statt die Summe still über die Kachel zu heben.
    $summe = (int) $zeiten->sum('minuten');
    $laufende = $zeiten->filter->laeuft();
@endphp

<div class="fi-logbuch">
    @foreach ($nachTagen as $tag => $desTages)
        @if ($mehrereTage)
            @php
                $datum = $desTages->first()->gestartet_am;
            @endphp

            <div class="mt-4 flex items-baseline justify-between border-b border-white/5 pb-1 first:mt-0">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ $datum->isToday() ? 'Heute' : $datum->translatedFormat('l, j.n.') }}
                </span>

                <span class="text-xs tabular-nums text-gray-500">
                    {{ Dauer::alsStunden((int) $desTages->sum('minuten')) }}
                </span>
            </div>
        @endif

        <div class="divide-y divide-white/5">
            @foreach ($desTages as $zeit)
                @php
                    $ticket = $zeit->ticket;
                    $eigene = $zeit->user?->is($ich) ?? false;
                    $laeuft = $zeit->laeuft();
                @endphp

                <div class="flex items-start gap-3 py-2.5">
                    <span class="w-24 shrink-0 pt-0.5 text-xs tabular-nums text-gray-500">
                        @if ($laeuft)
                            seit {{ $zeit->gestartet_am->format('H:i') }}
                        @else
                            {{ $zeit->gestartet_am->format('H:i') }}–{{ $zeit->beendet_am?->format('H:i') }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            @if ($mitNamen)
                                <span class="text-sm font-medium text-gray-100">
                                    {{ $eigene ? 'Du' : ($zeit->user?->name ?? 'Unbekannt') }}
                                </span>
                            @endif

                            @if ($ticket)
                                <a
                                    href="{{ TicketResource::getUrl('view', ['record' => $ticket]) }}"
                                    class="truncate text-sm text-gray-400 hover:text-primary-400"
                                >
                                    {{ $ticket->kennung() }} — {{ $ticket->titel }}
                                </a>
                            @else
                                <span class="text-sm text-gray-500">Ohne Ticket</span>
                            @endif
                        </div>

                        {{-- Das "was": die Beschreibung der Buchung. Fehlt sie,
                             steht statt einer leeren Zeile der Kunde da — das
                             Ticket darüber sagt dann bereits genug. --}}
                        @if (filled($zeit->beschreibung))
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ $zeit->beschreibung }}
                            </p>
                        @endif

                        @if ($ticket?->customer || $ticket?->project || ! $zeit->abrechenbar)
                            <p class="mt-0.5 truncate text-xs text-gray-500">
                                {{ collect([$ticket?->customer?->name, $ticket?->project?->name])->filter()->join(' · ') }}

                                @unless ($zeit->abrechenbar)
                                    <span class="text-gray-600">·</span> nicht abrechenbar
                                @endunless
                            </p>
                        @endif
                    </div>

                    <span @class([
                        'shrink-0 rounded-md px-2 py-1 text-xs font-medium tabular-nums ring-1',
                        'bg-success-500/10 text-success-400 ring-success-500/20' => $laeuft,
                        'bg-gray-800 text-gray-300 ring-gray-700' => ! $laeuft,
                    ])>
                        {{ $laeuft ? 'läuft' : Dauer::alsStunden((int) $zeit->minuten) }}
                    </span>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="mt-3 flex items-baseline justify-between border-t border-white/10 pt-3">
        <span class="text-sm text-gray-400">
            {{ $zeiten->count() }} {{ $zeiten->count() === 1 ? 'Buchung' : 'Buchungen' }}

            @if ($laufende->isNotEmpty())
                <span class="text-xs text-gray-500">
                    · {{ $laufende->count() }} {{ $laufende->count() === 1 ? 'Uhr läuft noch' : 'Uhren laufen noch' }}
                    und {{ $laufende->count() === 1 ? 'ist' : 'sind' }} nicht mitgezählt
                </span>
            @endif
        </span>

        <span class="text-sm font-medium tabular-nums text-gray-100">
            {{ Dauer::alsStunden($summe) }}
        </span>
    </div>
</div>
