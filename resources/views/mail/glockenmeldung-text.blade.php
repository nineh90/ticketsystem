{{-- Die Textfassung. Wer HTML abgeschaltet hat, liest genau das hier. --}}
NILS-DIGITAL · ND-DECK

{{ $titel }}
@if (filled($text))

{{ $text }}
@endif
@if (filled($url))

Ansehen:
{{ $url }}
@endif

--
@if ($fuerKunden)
Sie bekommen diese Mail, weil Sie sich dafuer eingetragen haben. Adresse,
Themen und Abschalten stehen unter "Mein Konto" in Ihrem Bereich.
@else
Diese Mail kommt, weil an deinem Zugang "E-Mail bei Meldungen" eingeschaltet
ist. Abschalten unter Maschinenraum -> Crew.
@endif
