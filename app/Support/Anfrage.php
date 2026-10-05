<?php

namespace App\Support;

use App\Enums\Betreuung;
use App\Models\Customer;
use App\Models\Kontakt;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aus einer Anfrage wird ein Kunde.
 *
 * Im Projekt "Anfragen" liegen Tickets von Leuten, die noch keine Kunden
 * sind. Wird daraus ein Auftrag, standen bisher Name und Adresse nur im
 * Ticket und mussten in die Kundenakte abgetippt werden. Hier steht der Weg
 * ohne Abtippen.
 *
 * Das Ticket selbst bleibt, wo es ist — Begründung in der Migration zu
 * tickets.interessent_id. Es bekommt nur den Verweis auf den Kunden.
 */
class Anfrage
{
    /** Lässt sich aus diesem Ticket ein Interessent machen? */
    public static function offenFuer(Ticket $ticket): bool
    {
        return $ticket->interessent_id === null
            && (filled($ticket->absender_email) || filled($ticket->absender_name));
    }

    /**
     * Der Kunde, bei dem diese Absenderadresse schon bekannt ist.
     *
     * Wer zum zweiten Mal über die Website schreibt — oder längst Kunde ist
     * und trotzdem das Kontaktformular nimmt —, soll nicht doppelt in der
     * Kundenliste stehen. Gesucht wird zuerst unter den Kontakten, dann in
     * der Adresse des Kunden selbst; Groß- und Kleinschreibung zählt bei
     * Mailadressen nicht.
     */
    public static function bekannterKunde(Ticket $ticket): ?Customer
    {
        $email = Str::lower(trim((string) $ticket->absender_email));

        if ($email === '') {
            return null;
        }

        $kontakt = Kontakt::query()
            ->whereRaw('lower(email) = ?', [$email])
            // Der Kunde, unter dem das Ticket liegt ("Eingang"), ist kein
            // Treffer: dorthin gehört die Anfrage ja schon.
            ->where('customer_id', '!=', $ticket->customer_id)
            ->orderByDesc('aktiv')
            ->oldest('id')
            ->first();

        if ($kontakt !== null) {
            return $kontakt->customer;
        }

        return Customer::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->whereKeyNot($ticket->customer_id)
            ->oldest('id')
            ->first();
    }

    /**
     * Neuen Interessenten samt Kontakt anlegen und am Ticket vermerken.
     *
     * @param  array{kundenname: string, kuerzel: string, kontakt_name?: ?string, email?: ?string}  $daten
     */
    public static function alsInteressent(Ticket $ticket, array $daten): Customer
    {
        // Alles oder nichts: ein Kunde ohne Kontakt oder ein Kontakt, auf
        // den das Ticket nicht zeigt, wäre genau die halbe Arbeit, die
        // dieser Handgriff ersparen soll.
        return DB::transaction(function () use ($ticket, $daten) {
            $email = filled($daten['email'] ?? null) ? trim($daten['email']) : null;

            $kunde = Customer::create([
                'name' => $daten['kundenname'],
                'slug' => self::freierSlug($daten['kundenname']),
                'kuerzel' => $daten['kuerzel'],
                'email' => $email,
                'betreuung' => Betreuung::Interessent,
            ]);

            self::kontaktSichern($kunde, $daten['kontakt_name'] ?? null, $email, hauptkontakt: true);

            $ticket->forceFill(['interessent_id' => $kunde->getKey()])->save();

            return $kunde;
        });
    }

    /**
     * Die Anfrage einem Kunden zuordnen, den es schon gibt.
     *
     * Einen Kontakt legt das nur an, wenn die Adresse dort noch fehlt — der
     * Normalfall ist ja gerade, dass sie über einen Kontakt gefunden wurde.
     */
    public static function zuordnen(Ticket $ticket, Customer $kunde, ?string $kontaktName = null, ?string $email = null): Customer
    {
        return DB::transaction(function () use ($ticket, $kunde, $kontaktName, $email) {
            self::kontaktSichern($kunde, $kontaktName, filled($email) ? trim($email) : null);

            $ticket->forceFill(['interessent_id' => $kunde->getKey()])->save();

            return $kunde;
        });
    }

    /**
     * Ein Kürzel, das noch frei ist: die ersten drei Buchstaben des Namens,
     * und wenn die vergeben sind, zwei davon mit einer Ziffer dahinter.
     *
     * Nur ein Vorschlag — im Formular lässt er sich überschreiben. Er soll
     * verhindern, dass der erste Klick auf "Anlegen" mit "Kürzel ist schon
     * vergeben" endet.
     */
    public static function kuerzelVorschlag(string $name): string
    {
        $basis = Str::upper(Str::of($name)->slug('', 'de')->toString());
        $basis = $basis === '' ? 'KD' : $basis;

        $kandidaten = [Str::substr($basis, 0, 3)];

        foreach (range(2, 9) as $ziffer) {
            $kandidaten[] = Str::substr($basis, 0, 2).$ziffer;
        }

        $vergeben = Customer::query()
            ->whereIn(DB::raw('upper(kuerzel)'), $kandidaten)
            ->pluck('kuerzel')
            ->map(fn (string $k) => Str::upper($k))
            ->all();

        foreach ($kandidaten as $kandidat) {
            if (Str::length($kandidat) >= 2 && ! in_array($kandidat, $vergeben, true)) {
                return $kandidat;
            }
        }

        // Alles belegt: dann leer lassen und fragen, statt etwas zu raten,
        // das danach in jeder Ticketnummer steht.
        return '';
    }

    /** Der Slug muss eindeutig sein; zwei "Müller" gibt es schneller als gedacht. */
    private static function freierSlug(string $name): string
    {
        $basis = Str::slug($name, '-', 'de') ?: 'kunde';
        $slug = $basis;
        $zaehler = 2;

        while (Customer::where('slug', $slug)->exists()) {
            $slug = $basis.'-'.$zaehler++;
        }

        return $slug;
    }

    private static function kontaktSichern(Customer $kunde, ?string $name, ?string $email, bool $hauptkontakt = false): void
    {
        $name = trim((string) $name);

        if ($name === '' && $email === null) {
            return;
        }

        if ($email !== null && $kunde->kontakte()->whereRaw('lower(email) = ?', [Str::lower($email)])->exists()) {
            return;
        }

        if ($email === null && $kunde->kontakte()->where('name', $name)->exists()) {
            return;
        }

        $kunde->kontakte()->create([
            // Ohne Namen bleibt nur die Adresse — besser als ein Kontakt,
            // der "Unbekannt" heißt und so stehen bleibt.
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'funktion' => 'Anfrage über die Website',
            'hauptkontakt' => $hauptkontakt,
        ]);
    }
}
