{{-- Die Textfassung. Wer HTML abgeschaltet hat, liest genau das hier.
     {!! !!} statt {{ }}: das ist text/plain, kein HTML. Escapen machte aus
     "Sozialrecht & Hilfesystem" ein "Sozialrecht &amp; Hilfesystem". --}}
NILS-DIGITAL · ND-DECK

{!! $betreff !!}
@if (filled($einleitung ?? null))
{!! $einleitung !!}
@endif
@foreach ($gruppen as $kunde => $zeilen)
@if (filled($kunde))

== {!! $kunde !!} ==
@endif
@foreach ($zeilen as $zeile)

* {!! $zeile['titel'] !!}
@if (filled($zeile['text']))
  {!! $zeile['text'] !!}
@endif
@if (filled($zeile['url']))
  {!! $zeile['url'] !!}
@endif
@endforeach
@endforeach

--
@if (filled($fussnote ?? null))
{!! $fussnote !!}
@elseif ($fuerKunden)
Wir fassen zusammen, statt fuer jede Kleinigkeit einzeln zu schreiben:
einmal am Morgen, sonntags gar nicht. Adresse, Themen und Abschalten
stehen unter "Mein Konto" in Ihrem Bereich.
@else
Gesammelt um 9 und um 17 Uhr, weil an deinem Zugang "E-Mail bei Meldungen"
eingeschaltet ist. Abschalten unter "Mein Zugang".
@endif
