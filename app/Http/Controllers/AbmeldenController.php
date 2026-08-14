<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Abmelden — gezielt aus einem der beiden Bereiche.
 *
 * Es gibt diese Route, weil Filaments eigene Abmeldung innerhalb des Panels
 * liegt und damit hinter dessen Schranke: wer canAccessPanel() nicht besteht,
 * bekommt schon auf dem Weg zur Abmeldung einen 403. Genau das ist aber die
 * Lage, in der man sich abmelden will — und ohne diese Route bliebe nur, die
 * Cookies von Hand zu löschen.
 *
 * Abgemeldet wird ausdrücklich nur der genannte Guard. Ein session()->
 * invalidate() würde beide Bereiche mitnehmen und damit gerade das kaputt
 * machen, wofür es die getrennten Guards gibt (siehe config/auth.php).
 */
class AbmeldenController extends Controller
{
    /** Bereichsname → Guard. Eine feste Liste, damit aus der URL kein Guard wird. */
    private const BEREICHE = [
        'intern' => 'web',
        'kunde' => 'kunde',
    ];

    public function __invoke(Request $request, string $bereich): RedirectResponse
    {
        abort_unless(isset(self::BEREICHE[$bereich]), 404);

        Auth::guard(self::BEREICHE[$bereich])->logout();

        // Neuer CSRF-Token, damit ein abgefangenes Formular der alten Sitzung
        // nicht weiterverwendet werden kann. Bewusst ohne invalidate(): die
        // Anmeldung des jeweils anderen Bereichs soll bestehen bleiben.
        $request->session()->regenerateToken();

        return redirect()->route($bereich === 'kunde'
            ? 'filament.kunde.auth.login'
            : 'filament.admin.auth.login');
    }
}
