<?php

namespace App\Support;

use App\Enums\DokumentArt;
use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Filament\Pages\Abrechnung as AbrechnungsSeite;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Dokument;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Was Geld kostet, wenn es niemand sieht.
 *
 * Die drei teuersten Vergessensfälle in diesem Betrieb sind keine Fehler,
 * sondern Stille: eine Rechnung, die überfällig ist und deren Frist niemand
 * nachhält; ein Angebot, das seit zwei Wochen unbeantwortet liegt und bei dem
 * ein Anruf genügt hätte; und Stunden, die gebucht, aber nie in Rechnung
 * gestellt wurden.
 *
 * Alle drei Zahlen rechnet das System längst aus — man muss nur die richtige
 * Seite öffnen. Diese Klasse dreht das um und schickt sie los.
 *
 * Empfänger sind die Administratoren. Das ist nicht Rangordnung, sondern
 * Zuständigkeit: Rechnungen schreibt und mahnt, wer den Betrieb führt, und
 * eine Meldung über unbezahlte Beträge an jemanden, der damit nichts zu tun
 * hat, ist eine Information über den Kontostand des Chefs.
 */
class Kasse
{
    /**
     * Wöchentlich: überfällige Rechnungen und liegende Angebote.
     *
     * Beides in einer Meldung, weil es dieselbe Frage ist ("wo hängt Geld?")
     * und weil zwei Mails am selben Morgen sich gegenseitig entwerten.
     */
    public static function fristen(): int
    {
        $rechnungen = Dokument::query()
            ->offen()
            ->rechnungen()
            ->whereNotNull('faellig_am')
            ->whereDate('faellig_am', '<', today())
            ->with('customer')
            ->get();

        $angebote = Dokument::query()
            ->offen()
            ->where('art', DokumentArt::Angebot->value)
            ->where('created_at', '<', now()->subDays(Wache::ANGEBOT_NACHFASSEN_TAGEN))
            ->with('customer')
            ->get();

        if ($rechnungen->isEmpty() && $angebote->isEmpty()) {
            return 0;
        }

        $admins = self::admins();

        if ($admins->isEmpty()) {
            return 0;
        }

        $zeilen = array_filter([
            $rechnungen->isEmpty() ? null : $rechnungen->count().' überfällige Rechnung'
                .($rechnungen->count() === 1 ? '' : 'en')
                .' ('.self::summe($rechnungen).')',
            $angebote->isEmpty() ? null : $angebote->count().' Angebot'
                .($angebote->count() === 1 ? '' : 'e').' seit über '
                .Wache::ANGEBOT_NACHFASSEN_TAGEN.' Tagen ohne Antwort',
        ]);

        // Der Knopf führt auf den Kunden mit dem ältesten offenen Posten:
        // eine Sammelliste über alle Dokumente gibt es nicht, und die
        // Kundenakte ist ohnehin der Ort, an dem man dann arbeitet.
        $aeltester = $rechnungen->sortBy('faellig_am')->first() ?? $angebote->sortBy('created_at')->first();

        Benachrichtigung::an(
            $admins,
            Notification::make()
                ->title('Offene Posten')
                ->body(implode(' · ', $zeilen))
                ->icon('heroicon-o-banknotes')
                ->color($rechnungen->isEmpty() ? 'warning' : 'danger')
                ->actions([
                    Benachrichtigung::knopf(
                        'Ältesten ansehen',
                        CustomerResource::getUrl('view', ['record' => $aeltester->customer_id], panel: 'admin'),
                    ),
                ]),
            null,
            null,
            MailEreignis::Kasse,
        );

        return $admins->count();
    }

    /**
     * Zum Monatsanfang: was noch auf keiner Rechnung steht.
     *
     * Die Zahl, die am ehesten Geld kostet, und die einzige hier, die man
     * ohne diese Meldung erst bemerkt, wenn man die Seite *Abrechnung*
     * aufruft — also frühestens dann, wenn man ohnehin schon Rechnungen
     * schreibt.
     *
     * Gerechnet wird je Administrator einzeln, nicht einmal für alle: die
     * Abrechnung filtert nach Sichtbarkeit, und eine Zahl, die für den
     * Empfänger nicht gilt, ist schlimmer als keine.
     */
    public static function monatsmeldung(): int
    {
        $gemeldet = 0;

        foreach (self::admins() as $admin) {
            $offen = Abrechnung::jeKunde($admin);

            if ($offen->isEmpty()) {
                continue;
            }

            $minuten = (int) $offen->sum('minuten');

            Benachrichtigung::an(
                collect([$admin]),
                Notification::make()
                    ->title(Dauer::alsStunden($minuten).' noch nicht abgerechnet')
                    ->body($offen->count().' Kunde'.($offen->count() === 1 ? '' : 'n')
                        .', ältester Posten vom '
                        .($offen->pluck('aeltester')->filter()->min()?->format('d.m.Y') ?? '—').'.')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->actions([
                        Benachrichtigung::knopf('Abrechnen', AbrechnungsSeite::getUrl(panel: 'admin')),
                    ]),
                null,
                null,
                MailEreignis::Kasse,
            );

            $gemeldet++;
        }

        return $gemeldet;
    }

    /** Die Summe mehrerer Dokumente, wie man sie ausspricht. */
    private static function summe(Collection $dokumente): string
    {
        $betrag = (float) $dokumente->sum(fn (Dokument $dokument) => (float) $dokument->betrag);

        return number_format($betrag, 2, ',', '.').' €';
    }

    /** @return Collection<int, User> */
    private static function admins(): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->where('rolle', Rolle::Admin->value)
            ->get();
    }
}
