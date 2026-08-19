{{--
    Der Kopf der Kundenübersicht.

    Bewusst keine Karte mit Rahmen und Schatten: das hier ist eine
    Überschrift, kein Inhalt. Ein Kasten drumherum machte aus der Begrüßung
    ein Bedienelement und schöbe die eigentlichen Informationen — Stand der
    Anliegen, Projekte — eine Reihe nach unten.

    Das Logo bekommt einen hellen Grund. Viele Logos sind für weiße Seiten
    gemacht und haben dunkle Schrift; auf dem dunklen Theme wären sie sonst
    unsichtbar. Ein weißes Feld ist die Fassung, die für alle funktioniert.
--}}
@php
    $kunde = $this->getKunde();
    $logo = $kunde?->logoUrl();
@endphp

<x-filament-widgets::widget>
    <div class="flex items-center gap-4">
        @if ($logo)
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <img
                    src="{{ $logo }}"
                    alt="{{ $kunde->name }}"
                    class="max-h-full max-w-full object-contain"
                />
            </div>
        @endif

        <div class="min-w-0">
            <p class="truncate text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                Moin, {{ $this->getVorname() }}
            </p>

            {{--
                Unser Name steht hier bewusst neben dem des Kunden. Das
                größte Bild auf dieser Seite ist sein Logo; ohne diese Zeile
                läse sich der Bereich wie seiner allein. "An Bord von
                Nils-Digital" sagt in vier Wörtern, wessen Schiff das ist —
                und der Firmenname dahinter, wer gerade darauf steht.

                Sind beide gleich, steht der Name nur einmal da: unser
                eigenes Konto ist als Kunde angelegt (zum Ausprobieren), und
                "An Bord von Nils-Digital · Nils-Digital" liest sich wie ein
                Fehler — was es auch wäre.
            --}}
            <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                An Bord von {{ $this->getReederei() }}@if ($kunde && $kunde->name !== $this->getReederei()) <span class="text-gray-400 dark:text-gray-600">·</span> {{ $kunde->name }}@endif
            </p>
        </div>
    </div>
</x-filament-widgets::widget>
