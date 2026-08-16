{{--
    Der Kopf über der Zeitentabelle eines Tickets.

    Achtung, das ist der Grund für die zweite Hälfte dieser Datei: ein eigener
    Tabellenkopf ERSETZT in Filament den eingebauten — samt Überschrift und
    samt der Knöpfe "Zeit starten", "Zeit stoppen" und "Zeit nachtragen"
    (vendor/filament/tables/resources/views/index.blade.php: @if ($header)).
    Ohne die Zeilen unten wäre die Zeiterfassung von hier aus nicht mehr zu
    bedienen. Deshalb steht hier beides: erst die laufenden Uhren, darunter
    der gewohnte Kopf.

    Die Liste selbst ist dieselbe wie auf dem Dashboard,
    siehe resources/views/filament/laufende-zeiten.blade.php.
--}}
@php
    $auffaellig = $zeiten->filter->laeuftAuffaelligLange()->count();

    $ueberschrift = $this->getTable()->getHeading();
    $aktionen = array_filter(
        $this->getTable()->getHeaderActions(),
        fn ($aktion) => $aktion->isVisible(),
    );
@endphp

{{-- Ohne eigenen unteren Rand: den zieht der eingebaute Kopf unten selbst,
     und zwei Linien im Abstand von einer Zeile sehen aus wie ein Fehler. --}}
<div class="px-4 pt-4 sm:px-6">
    <div class="mb-1 flex flex-wrap items-center gap-x-2">
        <x-filament::icon
            icon="heroicon-m-play-circle"
            @class([
                'h-4 w-4',
                'text-danger-400' => $auffaellig > 0,
                'text-success-400' => $auffaellig === 0,
            ])
        />

        <h3 class="text-sm font-medium text-gray-200">
            Läuft gerade
        </h3>

        <span class="text-xs text-gray-500">
            @if ($auffaellig > 0)
                {{ $auffaellig }} {{ $auffaellig === 1 ? 'Uhr läuft' : 'Uhren laufen' }} auffällig lange
            @else
                auch an anderen Tickets
            @endif
        </span>
    </div>

    @include('filament.laufende-zeiten', ['zeiten' => $zeiten, 'poll' => '60s'])

    @if ($ueberschrift || $aktionen)
        {{-- fi-ta-header-adaptive-actions-position ist das, was Überschrift
             und Knöpfe in eine Zeile legt; ohne die Klasse stehen sie
             untereinander. Filament setzt sie selbst, wenn die Knöpfe an
             ihrer Voreinstellung stehen — hier muss sie mit. --}}
        <div class="fi-ta-header fi-ta-header-adaptive-actions-position !px-0">
            @if ($ueberschrift)
                <h3 class="fi-ta-header-heading">{{ $ueberschrift }}</h3>
            @endif

            @if ($aktionen)
                <div class="fi-ta-actions fi-align-start fi-wrapped">
                    @foreach ($aktionen as $aktion)
                        {{ $aktion }}
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
