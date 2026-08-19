<?php

namespace App\Support;

use App\Enums\MailEreignis;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Treffen;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Wer zu einem Treffen gehört — und wer davon erfährt.
 *
 * Die eine Stelle, durch die das Einladen läuft. Sie steht hier und nicht im
 * Formular, weil es später mehr als einen Weg zu einem Treffen geben wird
 * (ein wiederkehrender Termin, ein Vorschlag aus einer Nachricht), und jeder
 * davon müsste sonst wissen, wen er zu benachrichtigen hat.
 */
class Messe
{
    /**
     * Setzt die Crew eines Treffens und meldet sich bei den Neuen.
     *
     * Nur bei den Neuen: wer schon dabei war, hat seine Einladung. Ein
     * Termin, an dem man dreimal die Beschreibung nachbessert, schickte
     * sonst dreimal dieselbe Meldung — und nach der zweiten liest sie
     * niemand mehr.
     *
     * @param  array<int, int|string>  $userIds
     */
    public static function crewSetzen(Treffen $treffen, array $userIds): void
    {
        $vorher = $treffen->crew()->pluck('users.id');

        $treffen->crew()->sync($userIds);

        $neue = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $vorher->contains($id));

        if ($neue->isEmpty()) {
            return;
        }

        $empfaenger = User::query()->whereKey($neue)->get();

        // Wer sich selbst einträgt, braucht darüber keine Meldung — er hat
        // das Formular gerade ausgefüllt.
        $empfaenger = $empfaenger->reject(fn (User $nutzer) => $nutzer->is(auth()->user()));

        if ($empfaenger->isEmpty()) {
            return;
        }

        self::anCrew($treffen, $empfaenger, 'Du bist dabei');
    }

    /**
     * Meldet allen aus der Crew etwas zu diesem Treffen.
     *
     * @param  Collection<int, User>|null  $nur  sonst die ganze Crew
     */
    public static function anCrew(
        Treffen $treffen,
        ?Collection $nur = null,
        string $anlass = 'Termin geändert',
        string $farbe = 'info',
    ): void {
        $empfaenger = $nur ?? $treffen->crew()->where('aktiv', true)->get();

        if ($empfaenger->isEmpty()) {
            return;
        }

        $treffen->loadMissing('customer');

        Benachrichtigung::an(
            $empfaenger,
            Notification::make()
                ->title($anlass.': '.$treffen->titel)
                ->body(self::wann($treffen).' · '.($treffen->customer?->name ?? 'ohne Kunde'))
                ->icon('heroicon-o-video-camera')
                ->color($farbe)
                ->actions([
                    Benachrichtigung::knopf('Ansehen', self::urlIntern($treffen)),
                ]),
            Herkunft::treffen($treffen),
            $treffen->customer,
            // Ein eigenes Thema und nicht MailEreignis::Treffen: das geht
            // nach außen. Wer hier Mail bekommt, hat "Meine Treffen"
            // angehakt — und kann genau das einzeln wieder abschalten,
            // ohne alles andere mit abzubestellen.
            MailEreignis::TreffenCrew,
        );
    }

    /** Der Termin in einem Satz, wie er in jeder Meldung steht. */
    public static function wann(Treffen $treffen): string
    {
        return $treffen->beginnt_am->translatedFormat('l, j. F Y \u\m H:i \U\h\r');
    }

    /**
     * Die Kundenakte, Reiter Messe.
     *
     * Es gibt keine eigene Seite je Treffen — der Termin steht in der Akte
     * des Kunden, und dorthin führt der Knopf.
     */
    public static function urlIntern(Treffen $treffen): string
    {
        return CustomerResource::getUrl(
            'view',
            ['record' => $treffen->customer_id],
            panel: 'admin',
        );
    }

    /**
     * Nur für die Vollständigkeit hier erwähnt: an den Kunden meldet der
     * TreffenObserver, und zwar mit MailEreignis::Treffen.
     */
    public static function mailEreignisFuerKunden(): MailEreignis
    {
        return MailEreignis::Treffen;
    }
}
