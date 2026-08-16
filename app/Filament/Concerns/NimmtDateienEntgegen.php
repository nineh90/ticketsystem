<?php

namespace App\Filament\Concerns;

use App\Filament\Formulare\Anhangfeld;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;

/**
 * Dateien, die beim Anlegen eines Tickets mitkommen.
 *
 * Das Feld dazu liefert Anhangfeld; hier steht, was danach passiert. Der Weg
 * ist zweigeteilt, weil er es sein muss: hochgeladen wird, bevor das Ticket
 * existiert, zugeordnet werden kann erst danach.
 *
 *   1. dateienAusFormular()  — vor dem Anlegen aus den Formulardaten nehmen
 *                              ("dateien" ist keine Spalte am Ticket)
 *   2. dateienAnhaengen()    — nach dem Anlegen aus dem Zwischenlager in den
 *                              Ordner des Tickets verschieben und je Datei
 *                              einen Anhang anlegen
 *   3. zwischenlagerAufraeumen() — beim Betreten des Formulars aufräumen, was
 *                              andere liegen gelassen haben
 *
 * Benutzt von CreateTicket (intern) und CreateAnliegen (Kundenbereich). Die
 * Ablage sieht danach in beiden Fällen gleich aus — das ist der Sinn der
 * gemeinsamen Fassung, denn ausgeliefert werden die Dateien später über
 * dieselbe Route.
 */
trait NimmtDateienEntgegen
{
    /**
     * Die hochgeladenen Dateien zwischen Schritt 1 und 2.
     *
     * @var list<string>
     */
    private array $dateien = [];

    /**
     * Schritt 1: die Dateien aus den Formulardaten nehmen.
     *
     * @param  array<string, mixed>  $daten
     * @return array<string, mixed>
     */
    protected function dateienAusFormular(array $daten, string $feld = 'dateien'): array
    {
        $this->dateien = array_values((array) ($daten[$feld] ?? []));

        unset($daten[$feld]);

        return $daten;
    }

    /**
     * Schritt 2: die Dateien an das eben angelegte Ticket hängen.
     *
     * Fehler beim Verschieben werden übersprungen und nicht hochgeworfen: das
     * Ticket ist zu diesem Zeitpunkt bereits angelegt und hat Benachrichtigungen
     * ausgelöst. Ein Abbruch hier hinterließe eine Fehlermeldung für etwas,
     * das in Wahrheit angekommen ist.
     */
    protected function dateienAnhaengen(Ticket $ticket): void
    {
        if ($this->dateien === []) {
            return;
        }

        $platte = Storage::disk(Attachment::PLATTE);

        foreach ($this->dateien as $eingang) {
            $ziel = 'anhaenge/'.$ticket->getKey().'/'.basename($eingang);

            if (! $platte->exists($eingang)) {
                continue;
            }

            if ($eingang !== $ziel && ! $platte->move($eingang, $ziel)) {
                continue;
            }

            $ticket->attachments()->create([
                'user_id' => auth()->id(),
                'pfad' => $ziel,
                'dateiname' => Anhangfeld::anzeigename($ziel),
                'mime' => $platte->mimeType($ziel) ?: null,
                'groesse' => $platte->size($ziel),
            ]);
        }

        $this->dateien = [];
    }

    /**
     * Liegengebliebene Dateien im Zwischenlager wegräumen.
     *
     * Filament legt einen Upload sofort ab, auch wenn das Formular danach nie
     * abgeschickt wird — wer einen Screenshot anhängt und es sich anders
     * überlegt, hinterlässt eine Datei, die zu nichts mehr gehört. Das ist
     * derselbe Fall, für den Attachment::booted() beim Löschen die Datei
     * mitnimmt: verwaiste Dateien lassen sich niemandem mehr zuordnen, und
     * sie enthalten unter Umständen genau das, was weg sollte.
     *
     * Der Aufräumer hängt am Aufruf des Formulars und nicht am Zeitplan: einen
     * Scheduler gibt es in diesem Projekt nicht (siehe deploy/entrypoint.sh),
     * ein schedule:run wäre also ein zusätzlicher Dauerprozess für eine
     * Handvoll Dateien. Wer ein Ticket anlegt, räumt beim Betreten das auf,
     * was jemand anders vor mehr als einem Tag stehengelassen hat.
     */
    protected function zwischenlagerAufraeumen(): void
    {
        $platte = Storage::disk(Attachment::PLATTE);
        $grenze = now()->subDay()->getTimestamp();

        foreach ($platte->files(Anhangfeld::ZWISCHENLAGER) as $datei) {
            if ($platte->lastModified($datei) < $grenze) {
                $platte->delete($datei);
            }
        }
    }
}
