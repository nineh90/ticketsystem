{{-- Die Textfassung. Wer HTML abgeschaltet hat, liest genau das hier. --}}
NILS-DIGITAL · TICKETSYSTEM

{{ $titel }}
@if (filled($text))

{{ $text }}
@endif
@if (filled($url))

Im Ticketsystem ansehen:
{{ $url }}
@endif

--
Diese Mail kommt, weil an deinem Zugang "E-Mail bei Meldungen" eingeschaltet
ist. Abschalten unter Verwaltung -> Nutzer.
