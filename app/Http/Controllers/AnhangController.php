<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert Anhänge aus — aber nur an Berechtigte.
 *
 * Die Dateien liegen außerhalb von public/, Apache kommt gar nicht an sie
 * heran. Jeder Abruf läuft hier durch und wird gegen die AttachmentPolicy
 * geprüft, die wiederum die Rechte am Ticket heranzieht.
 */
class AnhangController extends Controller
{
    public function __invoke(Attachment $anhang): StreamedResponse
    {
        // Gate::authorize statt $this->authorize(): der Basis-Controller
        // bringt das AuthorizesRequests-Trait seit Laravel 11 nicht mehr
        // mit, der Aufruf endete in einem 500er statt in einem 403.
        Gate::authorize('view', $anhang);

        abort_unless(
            Storage::disk(Attachment::PLATTE)->exists($anhang->pfad),
            404,
            'Die Datei ist nicht mehr vorhanden.',
        );

        // Bilder inline anzeigen, alles andere zum Herunterladen anbieten.
        // Ein PDF oder eine Textdatei inline auszuliefern hieße, fremden
        // Inhalt unter unserer Adresse darzustellen.
        $inline = $anhang->istBild();

        return Storage::disk(Attachment::PLATTE)->response(
            $anhang->pfad,
            $anhang->dateiname,
            [
                // nosniff: der Browser soll den Typ nicht raten. Sonst ließe
                // sich eine als Bild getarnte Datei doch als etwas anderes
                // ausführen.
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => $anhang->mime ?: 'application/octet-stream',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }
}
