{{--
    Der Reiseplan eines Projekts — die Etappen, die der Kunde sieht.

    Senkrecht und nicht waagerecht: die Etappen haben ungleich lange Namen
    und teils eine Erklärung darunter. Waagerecht nebeneinander wären das
    fünf verschieden hohe Spalten, die auf einem Telefon ohnehin umbrechen.

    Erledigtes wird nicht ausgegraut bis zur Unlesbarkeit. Es ist das, was wir
    geschafft haben — es soll dastehen.
--}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Meilenstein> $meilensteine */
    $letzter = $meilensteine->count() - 1;
@endphp

@if ($anteil !== null)
    <div class="mb-5">
        <div class="flex items-baseline justify-between text-sm">
            <span class="text-gray-500 dark:text-gray-400">
                {{ $meilensteine->whereNotNull('erledigt_at')->count() }} von {{ $meilensteine->count() }} Etappen geschafft
            </span>
            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">
                {{ $anteil }} %
            </span>
        </div>

        <div
            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
            role="progressbar"
            aria-valuenow="{{ $anteil }}"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            {{-- Mindestens 2 % breit, damit bei 0 % nicht der Eindruck
                 entsteht, der Balken sei kaputt. --}}
            <div
                class="h-full rounded-full bg-primary-500 transition-all"
                style="width: {{ max($anteil, 2) }}%"
            ></div>
        </div>
    </div>
@endif

<ol class="space-y-0">
    @foreach ($meilensteine as $i => $meilenstein)
        @php
            $erledigt = $meilenstein->istErledigt();
        @endphp

        <li class="relative flex gap-4 pb-6 last:pb-0">
            {{-- Die Linie zwischen den Punkten. Beim letzten weggelassen,
                 sonst zeigt sie ins Leere. --}}
            @if ($i !== $letzter)
                <div
                    class="absolute left-[11px] top-6 h-full w-px {{ $erledigt ? 'bg-primary-500/40' : 'bg-gray-200 dark:bg-white/10' }}"
                    aria-hidden="true"
                ></div>
            @endif

            <div class="relative z-10 mt-0.5 shrink-0">
                @if ($erledigt)
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-500 text-white">
                        <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                    </span>
                @else
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 ring-1 ring-inset ring-gray-300 dark:bg-white/5 dark:ring-white/20">
                        <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                    </span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium {{ $erledigt ? 'text-gray-950 dark:text-white' : 'text-gray-600 dark:text-gray-300' }}">
                    {{ $meilenstein->titel }}
                </p>

                @if (filled($meilenstein->beschreibung))
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ $meilenstein->beschreibung }}
                    </p>
                @endif

                {{-- Erledigt schlägt geplant: sobald es ein Datum gibt, an dem
                     es fertig war, interessiert das ursprünglich geplante
                     nicht mehr — und ein gerissener Termin daneben wäre eine
                     Diskussion, die niemand führen möchte. --}}
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($erledigt)
                        <span class="text-primary-500">Erledigt am {{ $meilenstein->erledigt_at->format('d.m.Y') }}</span>
                    @elseif ($meilenstein->faellig_am)
                        Geplant für {{ $meilenstein->faellig_am->format('d.m.Y') }}
                    @else
                        Steht noch aus
                    @endif
                </p>
            </div>
        </li>
    @endforeach
</ol>
