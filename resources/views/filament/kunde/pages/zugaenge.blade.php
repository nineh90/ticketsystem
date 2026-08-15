{{--
    Die Zugangsdaten-Seite des Kundenbereichs.

    Je Gruppe ein Abschnitt, damit auf einen Blick erkennbar ist, wozu ein
    Login gehört. Die Darstellung der einzelnen Einträge steckt in einer
    eigenen Ansicht, die auch die Projektseite einbindet — ein zweites Mal
    abgetippt wären es zwei Stellen, an denen das Passwort versehentlich
    offen stehen kann.
--}}
@php
    $gruppen = $this->getGruppen();
@endphp

<x-filament-panels::page>
    @if ($gruppen->isEmpty())
        <x-filament::section>
            <div class="py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-key"
                    class="mx-auto h-10 w-10 text-gray-400"
                />
                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                    Noch keine Zugangsdaten hinterlegt
                </p>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Sobald wir Ihnen einen Zugang einrichten — etwa zur Verwaltung Ihrer
                    Website —, finden Sie ihn hier. Dann müssen Sie ihn nicht in einer
                    alten Mail suchen.
                </p>
            </div>
        </x-filament::section>
    @else
        <div class="space-y-6">
            @foreach ($gruppen as $ueberschrift => $eintraege)
                <x-filament::section
                    :heading="$ueberschrift"
                    :description="$ueberschrift === 'Allgemein'
                        ? 'Gilt für Ihr Konto insgesamt.'
                        : 'Zugänge zu diesem Projekt.'"
                    icon="heroicon-o-key"
                >
                    @include('filament.kunde.zugangsdaten', ['eintraege' => $eintraege])
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Diese Daten sind verschlüsselt gespeichert und nur für Sie und uns
                sichtbar. Ändern Sie ein Passwort selbst, sagen Sie uns bitte
                Bescheid — sonst steht hier eines, das nicht mehr stimmt.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
