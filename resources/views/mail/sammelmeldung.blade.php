{{--
    Mehrere Meldungen in einer Mail — die Sammelmail und die Wochenmail.
    Einleitung und Fußzeile kann die Mail selbst mitbringen ($einleitung,
    $fussnote); ohne sie gelten die der Sammelmail. Dieselben Regeln wie bei
    glockenmeldung.blade.php: Tabellen, Stile am Element, heller Grund.
    Jede Zeile trägt ihren Farbstreifen links statt oben — dieselbe
    Unterscheidung wie der Punkt an der Glocke, nur je Meldung.
--}}
@php
    $streifen = fn (?string $farbe) => match ($farbe) {
        'danger' => '#d64545',
        'warning' => '#c98a12',
        'success' => '#2c8a5a',
        'info' => '#2d7d9a',
        default => '#00bcd4',
    };
    $schrift = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $betreff }}</title>
<style>
  @media only screen and (max-width: 620px) {
    .rahmen   { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
    .aussen   { padding: 0 !important; }
    .polster  { padding-left: 18px !important; padding-right: 18px !important; }
    .titel    { font-size: 19px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background:#eef3f5; -webkit-text-size-adjust:100%;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ collect($posten)->pluck('titel')->take(3)->implode(' · ') }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef3f5;">
<tr>
<td align="center" class="aussen" style="padding:24px 12px;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="rahmen" style="width:100%; max-width:600px; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #dce6ea;">

        <tr><td style="height:4px; line-height:4px; font-size:0; background:#00bcd4;">&nbsp;</td></tr>

        <tr>
        <td class="polster" style="padding:22px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="padding-right:9px;" valign="middle">
                    <img src="{{ asset('logo.png') }}" width="24" height="24" alt="" style="display:block; border:0; width:24px; height:24px;">
                </td>
                <td valign="middle">
                    <span style="font-family:{{ $schrift }}; font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0d7f8f;">Nils-Digital</span><span style="font-family:{{ $schrift }}; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#8aa0a8;">&nbsp;·&nbsp;ND-Deck</span>
                </td>
            </tr></table>
        </td>
        </tr>

        <tr>
        <td class="polster" style="padding:18px 30px 0 30px;">
            <h1 class="titel" style="margin:0; font-family:{{ $schrift }}; font-size:21px; line-height:1.3; font-weight:700; color:#12212a;">{{ $betreff }}</h1>
            <p style="margin:6px 0 0 0; font-family:{{ $schrift }}; font-size:14px; line-height:1.5; color:#5b7079;">
                @if (filled($einleitung ?? null))
                    {{ $einleitung }}
                @elseif ($fuerKunden)
                    Was sich seit unserer letzten Mail an Ihren Anliegen getan hat.
                @else
                    Was seit der letzten Sammelmail an der Glocke gelandet ist.
                @endif
            </p>
        </td>
        </tr>

        @foreach ($gruppen as $kunde => $zeilen)
        @if (filled($kunde))
        <tr>
        <td class="polster" style="padding:24px 30px 0 30px;">
            <p style="margin:0; font-family:{{ $schrift }}; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0d7f8f;">{{ $kunde }}</p>
        </td>
        </tr>
        @endif
        @foreach ($zeilen as $zeile)
        <tr>
        <td class="polster" style="padding:12px 30px 0 30px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                <td width="3" style="width:3px; background:{{ $streifen($zeile['farbe']) }}; border-radius:2px;">&nbsp;</td>
                <td style="padding:2px 0 2px 12px;">
                    <p style="margin:0; font-family:{{ $schrift }}; font-size:15px; line-height:1.4; font-weight:600; color:#12212a;">
                        @if (filled($zeile['url']))
                            <a href="{{ $zeile['url'] }}" style="color:#12212a; text-decoration:none;">{{ $zeile['titel'] }}</a>
                        @else
                            {{ $zeile['titel'] }}
                        @endif
                    </p>
                    @if (filled($zeile['text']))
                    <p style="margin:2px 0 0 0; font-family:{{ $schrift }}; font-size:14px; line-height:1.45; color:#3d525c;">{{ $zeile['text'] }}</p>
                    @endif
                    @if (filled($zeile['url']))
                    <p style="margin:3px 0 0 0; font-family:{{ $schrift }}; font-size:13px;"><a href="{{ $zeile['url'] }}" style="color:#0d7f8f; font-weight:600; text-decoration:none;">Ansehen&nbsp;→</a></p>
                    @endif
                </td>
            </tr></table>
        </td>
        </tr>
        @endforeach
        @endforeach

        <tr>
        <td class="polster" style="padding:26px 30px 24px 30px;">
            <div style="height:1px; line-height:1px; font-size:0; background:#e6eef1;">&nbsp;</div>
            <p style="margin:16px 0 0 0; font-family:{{ $schrift }}; font-size:12px; line-height:1.55; color:#8aa0a8;">
                @if (filled($fussnote ?? null))
                    {{ $fussnote }}
                @elseif ($fuerKunden)
                    Wir fassen zusammen, statt für jede Kleinigkeit einzeln zu schreiben: einmal am Morgen, sonntags gar nicht.
                    Adresse, Themen und Abschalten stehen unter <em>Mein&nbsp;Konto</em> in Ihrem Bereich.
                @else
                    Gesammelt um 9 und um 17 Uhr, weil an deinem Zugang <strong style="color:#5b7079;">E-Mail bei Meldungen</strong> eingeschaltet ist.
                    Abschalten unter <em>Mein&nbsp;Zugang</em>.
                @endif
            </p>
        </td>
        </tr>

    </table>

</td>
</tr>
</table>

</body>
</html>
