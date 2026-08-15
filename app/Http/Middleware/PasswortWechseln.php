<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Führt zum Profil, solange ein geschenktes Passwort in Gebrauch ist.
 *
 * Greift nur, wenn users.passwort_wechseln gesetzt ist — und das setzt sich
 * ausschließlich dann, wenn jemand anderes als der Kontoinhaber das Passwort
 * geändert hat (siehe User::booted). Wer sein Passwort selbst wählt, merkt
 * von dieser Middleware nie etwas.
 *
 * Es ist bewusst eine Umleitung und keine Sperre: der Nutzer landet auf einer
 * Seite, auf der er das Problem in zehn Sekunden selbst löst. Eine
 * Fehlermeldung "Ihr Passwort ist abgelaufen" ohne Weg dorthin wäre eine
 * Supportanfrage.
 */
class PasswortWechseln
{
    public function handle(Request $request, Closure $next): Response
    {
        $nutzer = Filament::auth()->user();

        if ($nutzer === null || ! $nutzer->passwort_wechseln) {
            return $next($request);
        }

        $panel = Filament::getCurrentOrDefaultPanel();
        $profil = $panel?->getProfileUrl();

        // Ohne Profilseite im Panel gäbe es kein Ziel — dann lieber
        // durchlassen als im Kreis leiten. Beide Panels haben eine; die
        // Prüfung ist für den Fall, dass jemand sie später abschaltet.
        if ($profil === null) {
            return $next($request);
        }

        if ($this->darfDurch($request, $profil)) {
            return $next($request);
        }

        Notification::make()
            ->title('Bitte vergeben Sie ein eigenes Passwort')
            ->body('Ihr jetziges Passwort haben wir Ihnen zugeteilt — es ist damit auch uns bekannt. Vergeben Sie hier eines, das nur Sie kennen.')
            ->warning()
            ->persistent()
            ->send();

        return redirect()->to($profil);
    }

    /**
     * Was trotz gesetztem Kennzeichen erreichbar bleiben muss.
     *
     * Die Livewire-Route ist der wichtigste Eintrag: das Profilformular
     * schickt seine Eingaben dorthin und nicht an die Profiladresse. Ohne
     * sie stünde der Nutzer auf der richtigen Seite und käme beim Klick auf
     * "Speichern" wieder auf ihr heraus — ohne dass etwas gespeichert würde.
     */
    private function darfDurch(Request $request, string $profil): bool
    {
        if ($request->fullUrlIs($profil.'*') || $request->url() === $profil) {
            return true;
        }

        // Abmelden muss immer gehen. Wer nicht wechseln will, soll gehen
        // können — sonst ist die einzige Auswahl das Löschen der Cookies.
        return $request->routeIs('filament.*.auth.logout')
            || $request->routeIs('livewire.*')
            || $request->is('livewire/*');
    }
}
