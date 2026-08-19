<?php

namespace App\Support;

use App\Mail\Adressbestaetigung as Mailfassung;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Der Weg, auf dem eine genannte Adresse zu einer belegten wird.
 *
 * An einer Stelle, weil beide Enden dieselbe Regel brauchen: die Prüfsumme,
 * die den Link an genau eine Adresse bindet. Ohne sie bliebe ein alter Link
 * gültig, nachdem jemand die Adresse geändert hat — und bestätigte damit
 * eine, die gar nicht mehr eingetragen ist.
 */
class Adressbestaetigung
{
    /** Wie lange ein Bestätigungslink gilt. */
    private const GUELTIG_TAGE = 3;

    /**
     * Die Bestätigungsmail losschicken.
     *
     * Nach der Antwort (defer), wie jede Mail hier — und mit demselben
     * Auffangnetz: scheitert der Versand, steht das im Protokoll, und die
     * Seite, auf der jemand gerade gespeichert hat, bleibt heil.
     */
    public static function anfordern(User $nutzer): void
    {
        if (blank($nutzer->benachrichtigungs_email)) {
            return;
        }

        $url = self::url($nutzer);
        $ziel = $nutzer->benachrichtigungs_email;

        defer(function () use ($nutzer, $ziel, $url) {
            try {
                Mail::to($ziel)->send(new Mailfassung($nutzer, $url));
            } catch (\Throwable $fehler) {
                Log::warning('Bestätigungsmail konnte nicht zugestellt werden.', [
                    'empfaenger' => $ziel,
                    'fehler' => $fehler->getMessage(),
                ]);
            }
        });
    }

    /**
     * Der signierte Link.
     *
     * Die Prüfsumme über die Adresse steckt mit in der Signatur: ändert der
     * Kunde sie, passt sie nicht mehr, und der alte Link läuft ins Leere. Das
     * ist der Fall, den man ohne sie übersieht — jemand trägt sich vertippt
     * ein, korrigiert es, und der erste Link bestätigt danach trotzdem.
     */
    public static function url(User $nutzer): string
    {
        return URL::temporarySignedRoute(
            'kunde.benachrichtigungen.bestaetigen',
            now()->addDays(self::GUELTIG_TAGE),
            [
                'nutzer' => $nutzer->getKey(),
                'pruefsumme' => self::pruefsumme($nutzer->benachrichtigungs_email),
            ],
        );
    }

    /** Passt der Link noch zu der Adresse, die gerade eingetragen ist? */
    public static function passt(User $nutzer, string $pruefsumme): bool
    {
        return filled($nutzer->benachrichtigungs_email)
            && hash_equals(self::pruefsumme($nutzer->benachrichtigungs_email), $pruefsumme);
    }

    private static function pruefsumme(?string $adresse): string
    {
        return substr(hash('sha256', (string) $adresse), 0, 16);
    }
}
