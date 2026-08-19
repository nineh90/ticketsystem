{{--
    Die Landeseite nach dem Klick aus der Bestätigungsmail.

    Eine eigene, schlichte Seite und keine Weiterleitung ins Panel: wer die
    Mail auf dem Telefon öffnet, ist dort nicht angemeldet und landete sonst
    auf der Anmeldemaske — ohne zu erfahren, ob die Bestätigung geklappt hat.
--}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $geklappt ? 'Adresse bestätigt' : 'Link nicht mehr gültig' }} — Nils-Digital</title>
<style>
  :root { color-scheme: light; }
  body {
    margin: 0; background: #eef3f5; color: #12212a;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px;
  }
  .karte {
    background: #fff; border: 1px solid #dce6ea; border-radius: 10px;
    max-width: 30rem; width: 100%; overflow: hidden;
  }
  .streifen { height: 4px; background: {{ $geklappt ? '#2c8a5a' : '#c98a12' }}; }
  .inhalt { padding: 26px 28px 30px; }
  .marke { font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0d7f8f; }
  .marke span { font-weight: 400; color: #8aa0a8; }
  h1 { font-size: 21px; margin: 14px 0 0; line-height: 1.3; }
  p { font-size: 16px; line-height: 1.55; color: #3d525c; margin: 12px 0 0; }
  .adresse { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 15px; color: #12212a; }
  a.knopf {
    display: inline-block; margin-top: 22px; padding: 11px 20px; border-radius: 7px;
    background: #00bcd4; color: #062a31; font-weight: 600; text-decoration: none; font-size: 15px;
  }
  a.knopf:focus-visible { outline: 2px solid #0d7f8f; outline-offset: 3px; }
</style>
</head>
<body>
<div class="karte">
    <div class="streifen"></div>
    <div class="inhalt">
        <div class="marke">Nils-Digital <span>· ND-Deck</span></div>

        @if ($geklappt)
            <h1>Adresse bestätigt</h1>
            <p>
                Wir schreiben Ihnen ab jetzt an <span class="adresse">{{ $adresse }}</span>,
                wenn sich bei Ihren Anliegen etwas tut.
            </p>
            <p>
                Was genau Sie erfahren möchten — und ob überhaupt — können Sie jederzeit
                unter <em>Mein Konto</em> ändern.
            </p>
        @else
            <h1>Dieser Link gilt nicht mehr</h1>
            <p>
                Das passiert, wenn er älter als drei Tage ist oder die Adresse inzwischen
                geändert wurde.
            </p>
            <p>
                Melden Sie sich einfach an und fordern Sie unter <em>Mein Konto</em> eine
                neue Bestätigung an.
            </p>
        @endif

        <a class="knopf" href="{{ url('/kunde') }}">Zum Kundenbereich</a>
    </div>
</div>
</body>
</html>
