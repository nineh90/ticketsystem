NILS-DIGITAL · ND-DECK

Neues Passwort vergeben

@if ($vorname !== '')Moin {{ $vorname }},@else Moin,@endif

@if ($siezen)
Sie möchten wieder in Ihren Bereich — hier ist der Weg hinein. Ein Klick,
ein neues Passwort, fertig.
@else
du willst wieder aufs Deck — hier ist der Weg hinein. Ein Klick, ein neues
Passwort, fertig.
@endif

{{-- {!! !!} und nicht {{ }}: in der Nur-Text-Fassung darf nichts maskiert
     werden. {{ }} macht aus dem & vor "signature" ein &amp;, und damit fehlt
     dem Aufruf die Signatur — Laravel weist ihn mit 403 ab. Am 01.09.2026
     stand das in allen drei Textmails und war nie aufgefallen, weil die
     HTML-Fassung daneben einwandfrei funktioniert und die meisten Programme
     sie anzeigen. Wer Text bevorzugt, kam nicht durch. --}}
Passwort vergeben:
{!! $url !!}

--
@if ($siezen)
Der Link gilt {{ $minuten }} Minuten. Haben Sie das nicht angefordert, können Sie
diese Mail einfach löschen — Ihr bisheriges Passwort bleibt dann unverändert
gültig.
@else
Der Link gilt {{ $minuten }} Minuten. Hast du das nicht angefordert, kannst du diese
Mail einfach löschen — dein bisheriges Passwort bleibt dann unverändert
gültig.
@endif
