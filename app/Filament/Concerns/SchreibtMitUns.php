<?php

namespace App\Filament\Concerns;

use App\Models\Nachricht;
use App\Models\Unterhaltung;
use App\Support\Unterhaltungen;
use Illuminate\Validation\ValidationException;

/**
 * Der eine Verlauf eines Kunden mit uns — lesen und schreiben.
 *
 * Stand bis NID-23 allein in der Seite "Nachrichten". Seit der Kundenbereich
 * nur noch einen Ort für "zu uns Kontakt aufnehmen" hat, liegt der Verlauf
 * auf der Kontaktseite; die alte Adresse bleibt bestehen, weil Meldungen und
 * Mails auf sie zeigen. Zwei Seiten, ein Verhalten — daher hier.
 *
 * Gehört zu views/filament/unterhaltung.blade.php: die Ansicht erwartet
 * $entwurf und senden() an der Seite, die sie einbindet.
 */
trait SchreibtMitUns
{
    /** Was im Eingabefeld steht. */
    public string $entwurf = '';

    /**
     * Der eine Verlauf dieses Kunden.
     *
     * Ohne Merker, damit eine gerade gesendete Nachricht im selben Aufbau
     * schon dabei ist.
     */
    public function verlauf(): Unterhaltung
    {
        $nutzer = auth()->user();

        // Ein Kundenzugang ohne Kundenzuordnung kommt gar nicht erst ins
        // Panel (User::canAccessPanel). Die Prüfung steht trotzdem hier:
        // ohne sie hinge die Zuordnung des Verlaufs an einer Bedingung, die
        // eine ganz andere Datei stellt.
        abort_if($nutzer?->customer_id === null, 403);

        return Unterhaltungen::fuerKunden($nutzer->customer_id)
            ->load(['nachrichten.absender', 'teilnehmer']);
    }

    public function senden(): void
    {
        $unterhaltung = $this->verlauf();

        if (auth()->user()?->cannot('schreiben', $unterhaltung)) {
            throw ValidationException::withMessages([
                'entwurf' => 'In diese Unterhaltung dürfen Sie nicht schreiben.',
            ]);
        }

        $text = trim($this->entwurf);

        if ($text === '') {
            return;
        }

        Nachricht::create([
            'unterhaltung_id' => $unterhaltung->getKey(),
            'user_id' => auth()->id(),
            'text' => $text,
        ]);

        $this->entwurf = '';
    }
}
