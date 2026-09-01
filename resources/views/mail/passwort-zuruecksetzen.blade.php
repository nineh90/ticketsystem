{{-- Dieselbe Bauart wie adressbestaetigung.blade.php — bewusst kopiert und
     nicht in ein gemeinsames Layout gezogen: Mail-HTML lebt von Tabellen und
     Inline-Stilen, und ein Layout, das drei Mails gleichzeitig bedienen soll,
     wird beim ersten Sonderfall zu einem Geflecht aus Bedingungen. Drei
     Dateien, die man nebeneinanderlegen kann, sind hier das kleinere Übel.

     (Und ja: eine Blade-Anweisung im Kommentar zu erwähnen, kostet einen
     Versuch — Blade übersetzt sie auch ohne Klammern und ohne sich am
     Kommentar zu stören.) --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Neues Passwort vergeben</title>
<style>
  @media only screen and (max-width: 620px) {
    .rahmen  { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
    .aussen  { padding: 0 !important; }
    .polster { padding-left: 18px !important; padding-right: 18px !important; }
    .titel   { font-size: 19px !important; }
    .knopf, .knopf a { display: block !important; width: 100% !important; text-align: center !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background:#eef3f5; -webkit-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">Ein Klick, dann vergeben Sie ein neues Passwort.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef3f5;">
<tr><td align="center" class="aussen" style="padding:24px 12px;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="rahmen" style="width:100%; max-width:600px; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #dce6ea;">

        <tr><td style="height:4px; line-height:4px; font-size:0; background:#00bcd4;">&nbsp;</td></tr>

        <tr><td class="polster" style="padding:22px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="padding-right:9px;" valign="middle">
                    <img src="{{ asset('logo.png') }}" width="24" height="24" alt="" style="display:block; border:0; width:24px; height:24px;">
                </td>
                <td valign="middle">
                    <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0d7f8f;">Nils-Digital</span><span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#8aa0a8;">&nbsp;·&nbsp;ND-Deck</span>
                </td>
            </tr></table>
        </td></tr>

        <tr><td class="polster" style="padding:18px 30px 0 30px;">
            <h1 class="titel" style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:21px; line-height:1.3; font-weight:700; color:#12212a;">Neues Passwort vergeben</h1>
        </td></tr>

        <tr><td class="polster" style="padding:12px 30px 0 30px;">
            <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.55; color:#3d525c;">
                @if ($vorname !== '')Moin {{ $vorname }},@else Moin,@endif
                @if ($siezen)
                    Sie möchten wieder in Ihren Bereich — hier ist der Weg hinein.
                    Ein Klick, ein neues Passwort, fertig.
                @else
                    du willst wieder aufs Deck — hier ist der Weg hinein.
                    Ein Klick, ein neues Passwort, fertig.
                @endif
            </p>
        </td></tr>

        <tr><td class="polster" style="padding:26px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="knopf"><tr>
            <td align="center" bgcolor="#00bcd4" class="knopf" style="border-radius:7px;">
                <a href="{{ $url }}" style="display:inline-block; padding:12px 22px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:600; color:#062a31; text-decoration:none; border-radius:7px;">Passwort vergeben</a>
            </td>
            </tr></table>
            <p style="margin:14px 0 0 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.5; color:#8aa0a8; word-break:break-all;">{{ $url }}</p>
        </td></tr>

        <tr><td class="polster" style="padding:26px 30px 24px 30px;">
            <div style="height:1px; line-height:1px; font-size:0; background:#e6eef1;">&nbsp;</div>
            <p style="margin:16px 0 0 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.55; color:#8aa0a8;">
                {{-- Die Frist steht ausdrücklich da. Wer die Mail abends öffnet und
                     den Link am nächsten Morgen anklickt, bekommt sonst eine
                     Fehlermeldung und hält seinen Zugang für kaputt. --}}
                {{-- Zwei ganze Sätze statt eingestreuter Bedingungen mitten im
                     Satz: Blade erkennt eine Anweisung nur, wenn vor dem @
                     kein Buchstabe steht. "Sie" gefolgt von einer
                     Else-Anweisung bleibt wörtlich stehen — kompiliert
                     anstandslos und fällt erst in der fertigen Mail auf. --}}
                @if ($siezen)
                    Der Link gilt {{ $minuten }} Minuten. Haben Sie das nicht angefordert,
                    können Sie diese Mail einfach löschen — Ihr bisheriges Passwort
                    bleibt dann unverändert gültig.
                @else
                    Der Link gilt {{ $minuten }} Minuten. Hast du das nicht angefordert,
                    kannst du diese Mail einfach löschen — dein bisheriges Passwort
                    bleibt dann unverändert gültig.
                @endif
            </p>
        </td></tr>

    </table>

</td></tr>
</table>
</body>
</html>
