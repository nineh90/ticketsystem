{{--
    Der Schriftzug im internen Panel — auf der Brücke.

    Hier steht "ND-Deck" und nicht "Nils-Digital": wer hier arbeitet, weiß,
    bei wem er arbeitet, und der Name benennt das Werkzeug. Genau darum ging
    es bei der Umbenennung — es ist längst kein Ticketsystem mehr.

    Der Kundenbereich bleibt bei "Nils-Digital" (siehe marke.blade.php). Ein
    Passagier kennt die Reederei, nicht den Namen der Software, mit der wir
    das Schiff fahren.

    Filament zeigt entweder brandLogo ODER brandName — sobald ein Logo
    gesetzt ist, verschwindet der Schriftzug. Diese Ansicht liefert deshalb
    beides als eine Einheit.
--}}
<div class="flex items-center gap-2">
    <img
        src="/logo.png"
        alt=""
        class="h-8 w-8 shrink-0"
    >
    <span
        class="text-lg font-semibold text-primary-500"
        style="font-family: 'Fredoka', system-ui, sans-serif;"
    >
        ND-Deck
    </span>
</div>
