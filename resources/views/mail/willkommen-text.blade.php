NILS-DIGITAL · ND-DECK

Willkommen an Bord

Moin {{ $name }}, Ihre Adresse {{ $adresse }} ist bestätigt. Diese Mail ist
der Beweis, dass der Weg funktioniert — ab jetzt hören Sie von uns, wenn sich
bei Ihren Anliegen etwas tut.
@if ($themen !== [])

Sie haben gewählt:
@foreach ($themen as $thema)
- {{ $thema }}
@endforeach
@endif

Zu Ihrem Bereich:
{{ $bereich }}

--
Adresse, Themen oder das Ganze abschalten: alles unter "Mein Konto" in Ihrem
Bereich.
