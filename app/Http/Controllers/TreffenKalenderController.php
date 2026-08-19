<?php

namespace App\Http\Controllers;

use App\Models\Treffen;
use App\Support\Kalender;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Gibt den Kalendereintrag zu einem Treffen aus.
 *
 * Dieselbe Bauart wie AnhangController und DokumentController: geschützte
 * Route, Prüfung über die Policy, je ein Pfad für innen und für /kunde. Der
 * zweite Pfad ist auch hier kein Sicherheitsmerkmal, sondern entscheidet bei
 * abgelaufener Sitzung, an welcher der beiden Anmeldungen jemand landet
 * (siehe bootstrap/app.php).
 *
 * Anders als bei Anhängen und Dokumenten liegt hier keine Datei — der
 * Eintrag entsteht bei jedem Abruf neu. Das ist der Punkt: verschiebt sich
 * ein Termin, ist der Eintrag hinter derselben Adresse sofort der richtige.
 */
class TreffenKalenderController extends Controller
{
    public function __invoke(Treffen $treffen): Response
    {
        Gate::authorize('view', $treffen);

        return response(Kalender::fuer($treffen), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Kalender::dateiname($treffen).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
