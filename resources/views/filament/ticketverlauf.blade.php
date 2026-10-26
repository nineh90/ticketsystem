{{--
    Die Kommentare an einem Ticket als Gespräch — siehe App\Livewire\Ticketverlauf.

    Drei Sorten, und man soll sie ohne Lesen unterscheiden können:
      gelb   interne Notiz — bleibt im Team
      cyan   von uns, für den Kunden sichtbar
      blau   vom Kunden
    Eine offene Frage trägt zusätzlich ihr Abzeichen, bis sie beantwortet ist.

    Liegt unter views/filament/, weil nur dieser Ordner als @source in der
    Theme-CSS steht (siehe unterhaltung.blade.php).
--}}
<div wire:poll.30s class="space-y-4">
    @forelse ($kommentare as $kommentar)
        @php
            $vomKunden = $kommentar->autor?->istKunde() ?? false;
            $art = match (true) {
                $vomKunden => 'kunde',
                $kommentar->ist_intern => 'intern',
                default => 'sichtbar',
            };
        @endphp

        <div
            wire:key="kommentar-{{ $kommentar->id }}"
            @class([
                'rounded-xl px-4 py-3 ring-1',
                'bg-warning-500/5 ring-warning-500/25' => $art === 'intern',
                'bg-primary-500/5 ring-primary-500/25' => $art === 'sichtbar',
                'bg-info-500/10 ring-info-500/30' => $art === 'kunde',
                'ring-2 ring-warning-500/60' => $kommentar->istOffeneFrage() && $kommentar->frage_an === $ich->id,
            ])
        >
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                <span class="font-semibold text-gray-100">
                    {{ $kommentar->autor?->name ?? 'Gelöschter Zugang' }}
                </span>

                <span @class([
                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium',
                    'bg-warning-500/15 text-warning-400' => $art === 'intern',
                    'bg-primary-500/15 text-primary-400' => $art === 'sichtbar',
                    'bg-info-500/15 text-info-400' => $art === 'kunde',
                ])>
                    <x-filament::icon
                        :icon="match ($art) {
                            'intern' => 'heroicon-m-lock-closed',
                            'sichtbar' => 'heroicon-m-eye',
                            default => 'heroicon-m-user',
                        }"
                        class="h-3 w-3"
                    />
                    {{ match ($art) {
                        'intern' => 'Interne Notiz',
                        'sichtbar' => 'Sieht der Kunde',
                        default => 'Vom Kunden',
                    } }}
                </span>

                @if ($kommentar->frage_an)
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium',
                        'bg-warning-500/20 text-warning-300' => $kommentar->istOffeneFrage(),
                        'bg-success-500/15 text-success-400' => ! $kommentar->istOffeneFrage(),
                    ])>
                        <x-filament::icon
                            :icon="$kommentar->istOffeneFrage() ? 'heroicon-m-question-mark-circle' : 'heroicon-m-check'"
                            class="h-3 w-3"
                        />
                        @if ($kommentar->istOffeneFrage())
                            Frage an {{ $kommentar->frage_an === $ich->id ? 'dich' : ($kommentar->gefragt?->name ?? '—') }}
                        @else
                            Beantwortet
                        @endif
                    </span>
                @endif

                <span class="text-gray-500 tabular-nums" title="{{ $kommentar->created_at->format('d.m.Y H:i') }}">
                    {{ $kommentar->created_at->format($kommentar->created_at->isToday() ? 'H:i' : 'd.m.Y H:i') }}
                </span>

                <span class="ms-auto flex items-center gap-3">
                    @if ($kommentar->istOffeneFrage() && ($ich->istAdmin() || $kommentar->frage_an === $ich->id || $kommentar->user_id === $ich->id))
                        <button type="button" wire:click="beantwortet({{ $kommentar->id }})" class="text-gray-400 hover:text-success-400">
                            Als beantwortet abhaken
                        </button>
                    @endif

                    @can('update', $kommentar)
                        <button type="button" wire:click="bearbeiten({{ $kommentar->id }})" class="text-gray-400 hover:text-primary-400">
                            Bearbeiten
                        </button>
                    @endcan

                    @can('delete', $kommentar)
                        <button
                            type="button"
                            wire:click="loeschen({{ $kommentar->id }})"
                            wire:confirm="Diesen Kommentar löschen?"
                            class="text-gray-400 hover:text-danger-400"
                        >
                            Löschen
                        </button>
                    @endcan
                </span>
            </div>

            @if ($bearbeitet === $kommentar->id)
                <form wire:submit="speichern" class="mt-2 space-y-2">
                    <textarea
                        wire:model="bearbeitetText"
                        rows="4"
                        class="w-full rounded-lg border-none bg-white/5 px-3 py-2 text-sm text-gray-100 ring-1 ring-white/10 focus:ring-2 focus:ring-primary-500"
                    ></textarea>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500">Speichern</button>
                        <button type="button" wire:click="abbrechen" class="rounded-lg px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200">Abbrechen</button>
                    </div>
                </form>
            @else
                {{-- Reiner Text: was hier steht, kann ein Kunde getippt haben.
                     Absätze bleiben über whitespace-pre-line erhalten (NID-31). --}}
                <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-gray-200">{{ $kommentar->body }}</p>
            @endif
        </div>
    @empty
        <p class="py-4 text-center text-sm text-gray-500">
            Noch nichts besprochen. Was zum Ticket geklärt wird, steht hier — unten der erste Satz.
        </p>
    @endforelse

    {{-- Schreiben. Enter schickt nicht ab: hier entstehen längere Texte mit
         Absätzen, anders als in den Nachrichten. --}}
    <form
        wire:submit="senden"
        x-data="{ intern: $wire.entangle('intern') }"
        class="space-y-3 border-t border-white/5 pt-4"
    >
        <textarea
            wire:model="entwurf"
            rows="4"
            placeholder="Kommentar schreiben …"
            class="w-full rounded-lg border-none bg-white/5 px-3 py-2 text-sm text-gray-100 ring-1 ring-white/10 placeholder:text-gray-500 focus:ring-2 focus:ring-primary-500"
        ></textarea>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
            <label class="flex items-center gap-2 text-sm text-gray-300">
                <input type="checkbox" x-model="intern" class="rounded border-none bg-white/10 text-warning-500 focus:ring-warning-500">
                <x-filament::icon icon="heroicon-m-lock-closed" class="h-4 w-4 text-warning-400" />
                Interne Notiz
            </label>

            <label x-show="intern" class="flex items-center gap-2 text-sm text-gray-300">
                <x-filament::icon icon="heroicon-m-question-mark-circle" class="h-4 w-4 text-warning-400" />
                Frage an
                <select
                    wire:model="frageAn"
                    class="rounded-lg border-none bg-white/5 py-1 pe-8 ps-2 text-sm text-gray-100 ring-1 ring-white/10 focus:ring-2 focus:ring-primary-500"
                >
                    <option value="">niemanden</option>
                    @foreach ($fragbare as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </label>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="senden"
                class="ms-auto flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:opacity-50"
            >
                <x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />
                Senden
            </button>
        </div>

        <p x-show="intern" class="text-xs text-gray-500">
            Bleibt im Team. Eine Frage geht sofort an die gefragte Person und bleibt offen, bis sie hier antwortet.
        </p>
        <p x-show="! intern" x-cloak class="text-xs text-primary-400">
            Der Kunde sieht diesen Kommentar in seinem Bereich als Antwort und wird darüber benachrichtigt.
        </p>
    </form>
</div>
