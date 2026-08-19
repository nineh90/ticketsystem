<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Adressbestaetigung;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Der Klick aus der Bestätigungsmail.
 *
 * Ohne Anmeldung erreichbar, und das ist Absicht: der Kunde öffnet die Mail
 * oft auf dem Telefon, wo er nicht angemeldet ist. Die Sicherheit hängt an
 * der Signatur des Links (Laravels signed middleware) und an der Prüfsumme
 * über die Adresse — nicht an einer Sitzung.
 *
 * Antwortet mit einer schlichten Seite statt einer Weiterleitung ins Panel:
 * wer nicht angemeldet ist, landete sonst auf der Anmeldemaske und wüsste
 * nicht, ob es geklappt hat.
 */
class BenachrichtigungBestaetigenController extends Controller
{
    public function __invoke(Request $request, User $nutzer, string $pruefsumme): View
    {
        // Die Prüfsumme gehört zur Adresse, nicht zum Link: eine geänderte
        // Adresse macht alte Links wertlos, obwohl ihre Signatur noch gilt.
        if (! $nutzer->istKunde() || ! Adressbestaetigung::passt($nutzer, $pruefsumme)) {
            return view('kunde.bestaetigt', [
                'geklappt' => false,
                'adresse' => null,
            ]);
        }

        // Nur beim ersten Klick begrüßen. Wer den Link zweimal öffnet — was
        // vorkommt, Mailprogramme laden Adressen manchmal von sich aus vor —
        // soll nicht zweimal begrüßt werden.
        if ($nutzer->benachrichtigungs_email_bestaetigt_at === null) {
            $nutzer->forceFill([
                'benachrichtigungs_email_bestaetigt_at' => now(),
            ])->save();

            Adressbestaetigung::willkommenSchicken($nutzer->fresh());
        }

        return view('kunde.bestaetigt', [
            'geklappt' => true,
            'adresse' => $nutzer->benachrichtigungs_email,
        ]);
    }
}
