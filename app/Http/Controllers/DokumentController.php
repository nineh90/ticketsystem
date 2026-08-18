<?php

namespace App\Http\Controllers;

use App\Models\Dokument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert Kundendokumente aus — aber nur an Berechtigte.
 *
 * Dasselbe Muster wie AnhangController und aus demselben Grund: die Dateien
 * liegen außerhalb von public/, jeder Abruf läuft hier durch und wird gegen
 * die DokumentPolicy geprüft. Hier wiegt es schwerer als beim Anhang — in
 * einem Angebot stehen Preise, und Dateinamen aus einem Buchhaltungsprogramm
 * sind fortlaufend und damit erratbar.
 */
class DokumentController extends Controller
{
    public function __invoke(Dokument $dokument): StreamedResponse
    {
        // Gate::authorize statt $this->authorize(): der Basis-Controller
        // bringt AuthorizesRequests seit Laravel 11 nicht mehr mit.
        Gate::authorize('view', $dokument);

        abort_unless(
            Storage::disk(Dokument::PLATTE)->exists($dokument->pfad),
            404,
            'Die Datei ist nicht mehr vorhanden.',
        );

        // Immer als Download, nie inline — anders als beim Anhang, wo Bilder
        // inline gehen. Ein PDF inline auszuliefern hieße, fremd erzeugten
        // Inhalt unter unserer Adresse darzustellen; das ist genau der Weg,
        // über den ein präpariertes PDF an die Sitzung käme.
        return Storage::disk(Dokument::PLATTE)->response(
            $dokument->pfad,
            $dokument->dateiname,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => $dokument->mime ?: 'application/octet-stream',
            ],
            'attachment',
        );
    }
}
