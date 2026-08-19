{{-- Dieselbe Bauart wie die übrigen Mails. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Sie sind eingetragen</title>
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
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">Ab jetzt hören Sie von uns, wenn sich etwas tut.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef3f5;">
<tr><td align="center" class="aussen" style="padding:24px 12px;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="rahmen" style="width:100%; max-width:600px; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #dce6ea;">

        <tr><td style="height:4px; line-height:4px; font-size:0; background:#2c8a5a;">&nbsp;</td></tr>

        <tr><td class="polster" style="padding:22px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="padding-right:9px;" valign="middle">
                    <img src="{{ asset('logo.png') }}" width="24" height="24" alt="" style="display:block; border:0; width:24px; height:24px;">
                </td>
                <td valign="middle">
                    <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0d7f8f;">Nils-Digital</span><span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#8aa0a8;">&nbsp;·&nbsp;Ticketsystem</span>
                </td>
            </tr></table>
        </td></tr>

        <tr><td class="polster" style="padding:18px 30px 0 30px;">
            <h1 class="titel" style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:21px; line-height:1.3; font-weight:700; color:#12212a;">Das hat geklappt</h1>
        </td></tr>

        <tr><td class="polster" style="padding:12px 30px 0 30px;">
            <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.55; color:#3d525c;">
                Hallo {{ $name }}, Ihre Adresse <strong style="color:#12212a;">{{ $adresse }}</strong>
                ist bestätigt. Diese Mail ist der Beweis, dass der Weg funktioniert — ab jetzt
                hören Sie von uns, wenn sich bei Ihren Anliegen etwas tut.
            </p>
        </td></tr>

        @if ($themen !== [])
        <tr><td class="polster" style="padding:18px 30px 0 30px;">
            <p style="margin:0 0 8px 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#8aa0a8;">Sie haben gewählt</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            @foreach ($themen as $thema)
                <tr><td style="padding:3px 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.5; color:#3d525c;">&bull;&nbsp;&nbsp;{{ $thema }}</td></tr>
            @endforeach
            </table>
        </td></tr>
        @endif

        <tr><td class="polster" style="padding:26px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="knopf"><tr>
            <td align="center" bgcolor="#00bcd4" class="knopf" style="border-radius:7px;">
                <a href="{{ $bereich }}" style="display:inline-block; padding:12px 22px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:600; color:#062a31; text-decoration:none; border-radius:7px;">Zu Ihrem Bereich</a>
            </td>
            </tr></table>
        </td></tr>

        <tr><td class="polster" style="padding:26px 30px 24px 30px;">
            <div style="height:1px; line-height:1px; font-size:0; background:#e6eef1;">&nbsp;</div>
            <p style="margin:16px 0 0 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.55; color:#8aa0a8;">
                Adresse, Themen oder das Ganze abschalten: alles unter <em>Mein Konto</em> in Ihrem Bereich.
            </p>
        </td></tr>

    </table>

</td></tr>
</table>
</body>
</html>
