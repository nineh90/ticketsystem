<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Nimmt das Kennzeichen "muss wechseln" weg, wenn jemand sein Passwort über
 * "Passwort vergessen" oder eine Einladung selbst gesetzt hat.
 *
 * Ohne diesen Zuhörer bliebe es stehen, und zwar aus einem feinen Grund: die
 * Regel dazu sitzt in User::booted und lautet "gesetzt, wenn ein ANDERER das
 * Passwort ändert". Sie liest den angemeldeten Nutzer — beim Zurücksetzen ist
 * aber niemand angemeldet, das ist ja der Anlass. Der Aufruf fällt damit
 * durch die frühe Rückkehr bei $handelnder === null, und das Kennzeichen
 * bleibt, wie es war.
 *
 * Die Folge wäre absurd und würde ausgerechnet den treffen, den wir gerade
 * hereinholen wollen: Der Kunde klickt die Einladung an, vergibt ein
 * Passwort, meldet sich damit an — und PasswortWechseln schickt ihn auf die
 * Profilseite mit der Aufforderung, ein eigenes Passwort zu vergeben. Das
 * hat er soeben getan.
 *
 * Sachlich ist das Kennzeichen an dieser Stelle ohnehin falsch: es bedeutet
 * "dieses Passwort ist auch uns bekannt". Wer den Link in seinem Postfach
 * anklickt und sich selbst eines ausdenkt, hat genau das nicht.
 *
 * Als Ereignis-Zuhörer und nicht als weitere Bedingung in User::booted: dort
 * ließe sich "niemand angemeldet" nicht von einem Konsolenbefehl oder einem
 * Seeder unterscheiden. Das Ereignis PasswordReset sagt eindeutig, was
 * passiert ist.
 */
class PasswortSelbstGesetzt
{
    public function handle(PasswordReset $ereignis): void
    {
        $nutzer = $ereignis->user;

        if (! $nutzer instanceof User || ! $nutzer->passwort_wechseln) {
            return;
        }

        // Über den Query Builder, damit updated_at unberührt bleibt und die
        // Zeile nicht ein zweites Mal durch User::booted läuft — dieselbe
        // Überlegung wie in AnmeldungMerken.
        User::query()
            ->whereKey($nutzer->getKey())
            ->update(['passwort_wechseln' => false]);

        // Auch am Objekt, das der Rest der Anfrage in der Hand hat: Filament
        // meldet den Nutzer nach dem Zurücksetzen gleich an, und die
        // Middleware PasswortWechseln sieht sonst noch den alten Wert.
        $nutzer->passwort_wechseln = false;
    }
}
