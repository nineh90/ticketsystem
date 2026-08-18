{{--
    Die Abrechnungsliste.

    Eine Tabelle und kein Kartenraster: die Zeilen sind gleich aufgebaut und
    werden von oben nach unten abgearbeitet. Genau dafür ist eine Tabelle da.
--}}
@php
    $zeilen = $this->getZeilen();
    $gesamt = $this->getGesamtMinuten();
@endphp

<x-filament-panels::page>
    @if ($zeilen->isEmpty())
        <x-filament::section>
            <div class="py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="mx-auto h-8 w-8 text-gray-500"
                />
                <p class="mt-2 text-sm font-medium text-gray-300">
                    @if ($this->ohneZuordnung())
                        Keine Zuordnung
                    @else
                        Nichts offen
                    @endif
                </p>
                <p class="mx-auto mt-1 max-w-md text-xs text-gray-500">
                    @if ($this->ohneZuordnung())
                        Dir ist noch kein Kunde und kein Projekt zugeordnet — deshalb ist hier nichts zu sehen.
                        Ein Administrator ordnet dich unter <strong>Verwaltung → Nutzer</strong> zu.
                    @else
                        {{-- Der Hinweis auf den Schalter ist wichtig: "nichts offen"
                             kann auch heißen, dass jemand alle Zeiten als nicht
                             abrechenbar gebucht hat. --}}
                        Alle abrechenbaren Zeiten stecken in einer Rechnung. Zeiten ohne den Haken
                        <em>abrechenbar</em> tauchen hier grundsätzlich nicht auf.
                    @endif
                </p>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $zeilen->count() === 1 ? 'Offen bei einem Kunden' : 'Offen bei '.$zeilen->count().' Kunden' }}</x-slot>
            <x-slot name="description">
                Zusammen {{ $this->alsStunden($gesamt) }}. Die Rechnung entsteht in sevDesk —
                hier steht, wofür.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-4 text-left font-medium">Kunde</th>
                            <th class="py-2 pr-4 text-right font-medium">Offen</th>
                            <th class="py-2 pr-4 text-right font-medium">Buchungen</th>
                            {{-- pl-8: die Zahl links ist rechtsbündig, das Datum hier
                                 linksbündig — ohne eigenen Abstand stoßen sie zusammen
                                 und lesen sich als eine Zahl ("3113.08.2026"). --}}
                            <th class="py-2 pl-8 pr-4 text-left font-medium">Ältester Eintrag</th>
                            <th class="py-2 text-right font-medium"><span class="sr-only">Öffnen</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($zeilen as $zeile)
                            <tr>
                                <td class="py-3 pr-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            style="background: {{ $zeile->kunde->farbe }}"
                                        ></span>
                                        <span class="font-medium text-gray-200">{{ $zeile->kunde->name }}</span>
                                        <span class="rounded bg-gray-800 px-1.5 py-0.5 font-mono text-xs text-gray-400">
                                            {{ $zeile->kunde->kuerzel }}
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3 pr-4 text-right font-mono tabular-nums text-gray-100">
                                    {{ $this->alsStunden($zeile->minuten) }}
                                </td>

                                <td class="py-3 pr-4 text-right font-mono tabular-nums text-gray-400">
                                    {{ $zeile->buchungen }}
                                </td>

                                <td class="py-3 pl-8 pr-4 text-gray-400">
                                    @if ($zeile->aeltester)
                                        {{ $zeile->aeltester->format('d.m.Y') }}
                                        {{-- Wie lange etwas schon liegt, ist die eigentliche
                                             Aussage: eine Woche ist normal, drei Monate sind
                                             vergessenes Geld. --}}
                                        <span class="text-xs text-gray-500">
                                            ({{ $zeile->aeltester->diffForHumans(short: true) }})
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="py-3 text-right">
                                    <x-filament::button
                                        size="xs"
                                        color="gray"
                                        tag="a"
                                        :href="$this->kundeUrl($zeile->kunde->getKey())"
                                        icon="heroicon-o-arrow-right"
                                    >
                                        Zur Akte
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Wie das Zuordnen läuft</x-slot>

            <ol class="ml-4 list-decimal space-y-1.5 text-sm text-gray-400">
                <li>Rechnung in sevDesk schreiben.</li>
                <li>In der Kundenakte unter <strong>Dokumente</strong> die PDF hochladen, Art <em>Rechnung</em>.</li>
                <li>Am Dokument <strong>Zeiten zuordnen</strong> — die offenen Buchungen stehen dort zur Auswahl.</li>
            </ol>

            <p class="mt-3 text-xs text-gray-500">
                Danach verschwinden sie aus dieser Liste. Wird die Rechnung gelöscht, sind sie
                wieder offen — sie sind dann ja auch nicht mehr abgerechnet.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
