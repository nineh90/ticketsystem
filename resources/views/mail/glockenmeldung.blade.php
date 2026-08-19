{{--
    Die Meldung als Mail.

    Tabellen statt Flexbox, Stile direkt am Element statt in einem
    Stylesheet, keine Webschrift und kein einziges Bild. Das sieht im
    Quelltext aus wie 2005 und ist trotzdem richtig: Outlook rendert mit der
    Word-Engine, Gmail wirft <style>-Blöcke weg, und Bilder sind in vielen
    Postfächern erst einmal blockiert. Was hier steht, kommt überall an.

    Hell und nicht dunkel, obwohl die Oberfläche dunkel ist: eine dunkle Mail
    ist in den verbreiteten Programmen ein Glücksspiel — mal wird sie
    invertiert, mal bleibt sie stehen, mal wird nur der Text hell. Ein heller
    Grund verhält sich überall gleich.
--}}
@php
    // Die Farbe aus der Meldung, in Werte übersetzt, die eine Mail versteht.
    // Dieselbe Unterscheidung wie der Punkt am Rand der Glocke.
    $streifen = match ($farbe) {
        'danger' => '#d64545',
        'warning' => '#c98a12',
        'success' => '#2c8a5a',
        'info' => '#2d7d9a',
        default => '#00bcd4',
    };
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $titel }}</title>
<style>
  /* Nur schmale Bildschirme. <style> überlebt nicht jedes Postfach — was
     hier steht, ist deshalb Zugabe: ohne die Regeln bleibt die Mail lesbar,
     nur mit mehr Rand. Die Grundlage macht die fluide Tabelle darunter. */
  @media only screen and (max-width: 620px) {
    .rahmen   { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
    .aussen   { padding: 0 !important; }
    .polster  { padding-left: 18px !important; padding-right: 18px !important; }
    .titel    { font-size: 19px !important; }
    .fliess   { font-size: 15px !important; }
    /* Der Knopf über die volle Breite: mit dem Daumen trifft man ihn sonst
       auf einem Telefon nur mit der Ecke. */
    .knopf, .knopf a { display: block !important; width: 100% !important; text-align: center !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background:#eef3f5; -webkit-text-size-adjust:100%;">

{{-- Vorschautext: was viele Postfächer neben dem Betreff anreißen. Ohne ihn
     stünde dort der Anfang des Fließtextes, also meist die Ticketnummer. --}}
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $text ?: 'Neue Meldung an Bord' }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef3f5;">
<tr>
<td align="center" class="aussen" style="padding:24px 12px;">

    {{-- width="100%" statt width="600": das Attribut setzen manche
         Postfaecher hart, und dann steht die Mail auf dem Telefon 600 Pixel
         breit in einem 320 Pixel breiten Fenster. Die Hoechstbreite macht
         das Stylesheet. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="rahmen" style="width:100%; max-width:600px; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #dce6ea;">

        {{-- Farbstreifen: die einzige Stelle, an der man ohne Lesen erkennt,
             worum es geht. --}}
        <tr><td style="height:4px; line-height:4px; font-size:0; background:{{ $streifen }};">&nbsp;</td></tr>

        {{-- Kopf. Wortmarke statt Logo — ein blockiertes Bild hinterlässt
             sonst ein leeres Kästchen an der auffälligsten Stelle. --}}
        <tr>
        <td class="polster" style="padding:22px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                {{-- Das eigene Logo. Wird es blockiert, bleibt die Wortmarke
                     daneben stehen — deshalb steht sie als Text da und nicht
                     im Bild. --}}
                <td style="padding-right:9px;" valign="middle">
                    <img src="{{ asset('logo.png') }}" width="24" height="24" alt="" style="display:block; border:0; width:24px; height:24px;">
                </td>
                <td valign="middle">
                    <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0d7f8f;">Nils-Digital</span><span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#8aa0a8;">&nbsp;·&nbsp;ND-Deck</span>
                </td>
            </tr></table>
        </td>
        </tr>

        <tr>
        <td class="polster" style="padding:18px 30px 0 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>
                @if (filled($kundenLogo))
                {{-- Das Logo des Kunden, um den es geht. Reine Lesehilfe: der
                     Name steht ohnehin im Titel, und blockierte Bilder sind
                     der Normalfall, nicht die Ausnahme. --}}
                <td width="44" valign="top" style="padding-right:14px;">
                    <img src="{{ $kundenLogo }}" width="44" height="44" alt="{{ $kundenName }}" style="display:block; border:0; width:44px; height:44px; border-radius:8px; object-fit:cover; background:#eef3f5;">
                </td>
                @endif
                <td valign="middle">
                    <h1 class="titel" style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:21px; line-height:1.3; font-weight:700; color:#12212a;">{{ $titel }}</h1>
                </td>
            </tr></table>
        </td>
        </tr>

        @if (filled($text))
        <tr>
        <td class="polster" style="padding:12px 30px 0 30px;">
            <p class="fliess" style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.55; color:#3d525c;">{{ $text }}</p>
        </td>
        </tr>
        @endif

        @if (filled($url))
        <tr>
        <td class="polster" style="padding:26px 30px 0 30px;">
            {{-- Knopf als Tabelle mit Hintergrundfarbe an der Zelle: ein
                 gestyltes <a> allein bleibt in Outlook ein blauer Link. --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="knopf">
            <tr>
            <td align="center" bgcolor="#00bcd4" class="knopf" style="border-radius:7px;">
                <a href="{{ $url }}" style="display:inline-block; padding:12px 22px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:600; color:#062a31; text-decoration:none; border-radius:7px;">Ansehen</a>
            </td>
            </tr>
            </table>

            {{-- Die Adresse zusätzlich im Klartext: Knöpfe überstehen nicht
                 jede Weiterleitung, und manche Postfächer zeigen gar keine. --}}
            <p style="margin:14px 0 0 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.5; color:#8aa0a8; word-break:break-all;">{{ $url }}</p>
        </td>
        </tr>
        @endif

        <tr>
        <td class="polster" style="padding:26px 30px 24px 30px;">
            <div style="height:1px; line-height:1px; font-size:0; background:#e6eef1;">&nbsp;</div>
            <p style="margin:16px 0 0 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.55; color:#8aa0a8;">
                @if ($fuerKunden)
                    Sie bekommen diese Mail, weil Sie sich dafür eingetragen haben.
                    Adresse, Themen und Abschalten stehen unter <em>Mein&nbsp;Konto</em> in Ihrem Bereich.
                @else
                    Diese Mail kommt, weil an deinem Zugang <strong style="color:#5b7079;">E-Mail bei Meldungen</strong> eingeschaltet ist.
                    Abschalten unter <em>Maschinenraum&nbsp;→&nbsp;Crew</em>.
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
