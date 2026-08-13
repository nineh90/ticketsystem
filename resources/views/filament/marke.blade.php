{{--
    Logo und Schriftzug nebeneinander, wie im Kopf von nils-digital.de.

    Filament zeigt entweder brandLogo ODER brandName — sobald ein Logo gesetzt
    ist, verschwindet der Schriftzug. Diese Ansicht liefert deshalb beides als
    eine Einheit.
--}}
<div class="flex items-center gap-2">
    <img
        src="{{ asset('logo.png') }}"
        alt=""
        class="h-8 w-8 shrink-0"
    >
    <span
        class="text-lg font-semibold text-primary-500"
        style="font-family: 'Fredoka', system-ui, sans-serif;"
    >
        Nils-Digital
    </span>
</div>
