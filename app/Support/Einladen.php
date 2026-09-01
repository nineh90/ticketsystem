<?php

namespace App\Support;

use App\Mail\Einladung;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * "Sie haben jetzt einen Zugang" — die Einladung an einen neuen Kundenzugang.
 *
 * Sie ersetzt einen Handgriff, der bis zum 01.09.2026 nötig war: ein
 * Startpasswort vergeben, es notieren, es weitergeben (siehe Startpasswort).
 * Das hatte drei Haken, und jeder für sich hat schon Kunden gekostet.
 *
 * Erstens ging das erste Passwort durch einen menschlichen Kanal und war
 * damit auch uns bekannt — deshalb der Wechselzwang beim ersten Anmelden
 * (PasswortWechseln), und deshalb ist das Erste, was ein neuer Kunde von
 * seinem Bereich sieht, ein Formular. Zweitens setzt es voraus, dass jemand
 * das Weitergeben tatsächlich tut; von fünf Kundenzugängen hatten sich am
 * 01.09.2026 zwei noch nie angemeldet. Drittens sagt es uns nie, ob die
 * Adresse überhaupt stimmt.
 *
 * Die Einladung löst alle drei auf einmal: der Kunde vergibt sein Passwort
 * selbst (wir kennen es nie), er braucht nichts abzutippen, und dass die Mail
 * ankommt, ist der Beweis für die Adresse.
 *
 * Technisch ist es derselbe Weg wie "Passwort vergessen" — bewusst. Ein
 * zweites Einladungs-Token mit eigener Tabelle, eigener Frist und eigener
 * Seite wäre ein zweiter Weg, der dasselbe tut und getrennt gepflegt werden
 * müsste. Dass der Link deshalb nur eine Stunde gilt, ist verkraftbar,
 * seit "Passwort vergessen" existiert: läuft er ab, holt sich der Kunde
 * selbst einen neuen. Genau das steht auch in der Mail.
 */
class Einladen
{
    /**
     * Verschickt die Einladung — und sagt, ob es geklappt hat.
     *
     * Kein Werfen bei einem Mailfehler: der Zugang ist zu dem Zeitpunkt schon
     * angelegt, und eine Ausnahme ließe das Formular so aussehen, als wäre
     * auch das Anlegen schiefgegangen. Der Aufrufer entscheidet, was er
     * meldet.
     */
    public static function schicken(User $nutzer): bool
    {
        if (blank($nutzer->email)) {
            return false;
        }

        // Ausdrücklich das Kundenpanel und nicht das gerade laufende: der
        // Aufruf kommt aus dem INTERNEN Bereich (Kunden → Zugänge). Ohne
        // diese Zeile trüge die Einladung einen Link auf die interne
        // Anmeldung — die den Kunden mit seinem frisch gesetzten, richtigen
        // Passwort abweist (User::canAccessPanel).
        $vorher = Filament::getCurrentPanel();
        Filament::setCurrentPanel('kunde');

        try {
            // Derselbe Broker, den das Kundenpanel zum Einlösen benutzt
            // (KundePanelProvider->authPasswordBroker). Nähme man hier den
            // Standard, entstünde das Token unter der Ein-Stunden-Frist und
            // wäre bei der Einladung nach genau dieser Zeit tot — die Frist
            // steckt im Broker, nicht im Token.
            $token = Password::broker('kunde')->createToken($nutzer);
            $url = Filament::getResetPasswordUrl($token, $nutzer);

            Mail::to($nutzer->email)->send(new Einladung($nutzer, $url));

            return true;
        } catch (\Throwable $fehler) {
            Log::warning('Einladung konnte nicht zugestellt werden.', [
                'nutzer' => $nutzer->getKey(),
                'fehler' => $fehler->getMessage(),
            ]);

            return false;
        } finally {
            // Zurücksetzen, sonst steht der Rest der Anfrage im falschen
            // Panel — und die nächste Adresse, die Filament erzeugt, zeigt
            // in den Kundenbereich.
            if ($vorher !== null) {
                Filament::setCurrentPanel($vorher);
            }
        }
    }
}
