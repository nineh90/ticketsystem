<?php

namespace App\Observers;

use App\Models\Dokument;
use App\Support\Automatik;
use Filament\Notifications\Notification;

/**
 * Was passiert, wenn ein Angebot angenommen wird.
 *
 * Am Modell und nicht am Knopf im Kundenbereich: den Stand setzt der Kunde
 * über seine beiden Knöpfe, aber genauso oft tun wir es für ihn — er sagt am
 * Telefon zu, und jemand trägt es nach. Beide Wege müssen dasselbe auslösen.
 *
 * Die Meldung an uns ("Angebot angenommen") bleibt, wo sie ist: sie gehört
 * zur Antwort des Kunden und kennt den Weg, auf dem sie kam. Hier steht nur,
 * was daraus folgt.
 */
class DokumentObserver
{
    public function updated(Dokument $dokument): void
    {
        if (! $dokument->wasChanged('stand')) {
            return;
        }

        $ticket = Automatik::folgeticket($dokument);

        if ($ticket === null) {
            return;
        }

        // An den, der gerade zusagt oder nachträgt — beim Kunden ist das die
        // Bestätigung, dass die Arbeit angefangen hat, bei uns die Antwort
        // auf "muss ich jetzt noch ein Ticket anlegen?". Nein, muss man nicht.
        //
        // Nur wenn jemand angemeldet ist: kommt der Stand aus einem Befehl
        // oder über die Schnittstelle, gibt es niemanden, dem man etwas
        // einblenden könnte — und keine Sitzung, in die es passte.
        if (! auth()->check()) {
            return;
        }

        Notification::make()
            ->title('Auftrag angelegt: '.$ticket->kennung())
            ->body($ticket->titel)
            ->success()
            ->send();
    }
}
