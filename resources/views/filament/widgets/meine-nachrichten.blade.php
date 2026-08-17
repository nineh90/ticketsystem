@php
    use App\Filament\Pages\Nachrichten;

    $offene = $this->getOffene();
    $gesamt = $offene->sum('ungelesen');
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-chat-bubble-left-right"
        icon-color="primary"
        heading="Ungelesene Nachrichten"
        :description="$gesamt === 1 ? 'Eine Nachricht wartet auf Antwort' : $gesamt . ' Nachrichten warten auf Antwort'"
    >
        @if ($offene->isEmpty())
            <p class="py-2 text-sm text-gray-500">
                Alles gelesen. Die Karte verschwindet beim nächsten Laden der Seite.
            </p>
        @else
            <div class="divide-y divide-white/5">
                @foreach ($offene as $zeile)
                    <a
                        href="{{ Nachrichten::getUrl(['unterhaltung' => $zeile['unterhaltung']->getKey()]) }}"
                        class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 transition hover:bg-white/5"
                    >
                        <x-filament::icon
                            :icon="$zeile['unterhaltung']->istIntern() ? 'heroicon-m-user' : 'heroicon-m-building-office-2'"
                            class="h-4 w-4 shrink-0 text-gray-500"
                        />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-100">{{ $zeile['titel'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $zeile['unterhaltung']->letzte_nachricht_am?->diffForHumans() }}
                            </p>
                        </div>

                        <span class="shrink-0 rounded-full bg-primary-500 px-2 py-0.5 text-xs font-semibold text-white tabular-nums">
                            {{ $zeile['ungelesen'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
