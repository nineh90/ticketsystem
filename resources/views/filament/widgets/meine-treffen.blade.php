{{--
    Meine nächsten Treffen auf der Wache.

    Knapper als die Karte im Kundenbereich: hier ist es eine Liste unter
    vielen, und wer intern arbeitet, braucht die Uhrzeit und den Knopf — den
    Rest weiß er.

    Das Wurzelelement steht immer da (Livewire bricht sonst ab); ob es die
    Karte überhaupt gibt, entscheidet canView().
--}}
@php
    $treffen = $this->getTreffen();
@endphp

<x-filament-widgets::widget>
    @if ($this->istSichtbar())
        <x-filament::section
            icon="heroicon-o-video-camera"
            heading="Meine Treffen"
            description="Termine, bei denen du dabei bist."
        >
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($treffen as $eintrag)
                    @php $laeuft = $eintrag->laeuft(); @endphp

                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
                        <div class="w-32 shrink-0">
                            <p @class([
                                'text-sm font-semibold tabular-nums',
                                'text-success-600 dark:text-success-400' => $laeuft,
                                'text-gray-950 dark:text-white' => ! $laeuft,
                            ])>
                                {{ $eintrag->beginnt_am->format('d.m. H:i') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $laeuft ? 'läuft gerade' : $eintrag->beginnt_am->diffForHumans() }}
                            </p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $eintrag->titel }}
                            </p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $eintrag->customer?->name }}
                                @unless ($eintrag->kunden_sichtbar)
                                    <span class="text-warning-600 dark:text-warning-400">
                                        · noch nicht eingeladen
                                    </span>
                                @endunless
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if (filled($eintrag->url))
                                <x-filament::button
                                    tag="a"
                                    :href="$eintrag->url"
                                    target="_blank"
                                    size="sm"
                                    :color="$laeuft ? 'success' : 'gray'"
                                    icon="heroicon-o-video-camera"
                                >
                                    An Bord
                                </x-filament::button>
                            @endif

                            {{-- Google und nicht die Datei: unsere Kalender
                                 liegen dort, und ein Klick schlaegt einen
                                 Download, den man erst noch oeffnen muss.
                                 Die .ics gibt es weiter auf der Messe. --}}
                            <x-filament::button
                                tag="a"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-calendar-days"
                                target="_blank"
                                :href="\App\Support\Kalender::googleUrl($eintrag)"
                            >
                                Kalender
                            </x-filament::button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
