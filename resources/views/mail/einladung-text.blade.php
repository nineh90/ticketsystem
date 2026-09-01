NILS-DIGITAL · ND-DECK

Willkommen an Bord

@if ($vorname !== '')Moin {{ $vorname }},@else Moin,@endif

wir haben @if ($firma)für {{ $firma }} @endif einen Zugang zu Ihrem Bereich
eingerichtet. Dort sehen Sie, woran wir gerade arbeiten, wie weit Ihre
Projekte sind, und Sie können uns etwas melden.

Vergeben Sie sich zuerst ein Passwort — eines, das nur Sie kennen.

{{-- {!! !!} und nicht {{ }}: sonst wird aus dem & vor "signature" ein &amp;
     und der Link läuft in einen 403. Siehe TextmailLinksTest. --}}
Passwort vergeben:
{!! $url !!}

--
Der Link gilt {{ $tage }} Tage. Ist er abgelaufen, öffnen Sie
{!! $anmeldung !!}
und klicken dort auf "Passwort vergessen?" — dann kommt ein neuer.
Fragen? Antworten Sie einfach auf diese Mail.
