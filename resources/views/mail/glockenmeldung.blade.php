{{--
    Die Meldung als Mail. Bewusst schlicht: Laravels Markdown-Vorlage
    braucht nichts Veröffentlichtes und sieht in jedem Postfach gleich aus.
--}}
<x-mail::message>
# {{ $titel }}

@if (filled($text))
{{ $text }}
@endif

@if (filled($url))
<x-mail::button :url="$url">Im Ticketsystem ansehen</x-mail::button>
@endif

<small>Diese Mail kommt, weil an deinem Zugang **E-Mail bei Meldungen** eingeschaltet ist.
Abschalten unter *Verwaltung → Nutzer*.</small>
</x-mail::message>
