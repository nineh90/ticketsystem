<?php

namespace App\Support;

use App\Enums\Unterhaltungsart;
use App\Models\Customer;
use App\Models\Nachricht;
use App\Models\Unterhaltung;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Woher eine Unterhaltung kommt und wie viel darin ungelesen ist.
 *
 * Beides an einer Stelle, weil es an drei Stellen gebraucht wird — auf der
 * internen Seite, im Kundenbereich und an der Zahl neben dem Menüpunkt. Vor
 * allem das Anlegen gehört hierher: eine zweite Kundenunterhaltung neben der
 * ersten wäre der Fehler, bei dem die Antwort im anderen Faden steht und
 * niemand versteht, warum. Die Datenbank verhindert ihn zwar (unique auf
 * customer_id), aber eine Ausnahme im Gesicht des Kunden ist keine Antwort.
 */
class Unterhaltungen
{
    /**
     * Der Faden mit diesem Kunden — vorhanden oder neu.
     *
     * Er entsteht beim ersten Öffnen und nicht erst beim ersten Wort. Sonst
     * müsste jede Ansicht mit einem Objekt umgehen können, das es noch nicht
     * gibt. Aus der Liste bleibt er heraus, solange nichts darin steht.
     */
    public static function fuerKunden(Customer|int $kunde): Unterhaltung
    {
        $id = $kunde instanceof Customer ? $kunde->getKey() : $kunde;

        return Unterhaltung::query()->firstOrCreate(
            ['customer_id' => $id],
            ['art' => Unterhaltungsart::Kunde],
        );
    }

    /**
     * Der interne Faden zwischen zwei Kolleginnen oder Kollegen.
     *
     * Gesucht wird über beide Beteiligten gleichzeitig — sonst fände man den
     * Faden nur aus der Richtung, aus der er angelegt wurde, und die zweite
     * Nachricht begänne eine zweite Unterhaltung.
     */
    public static function zwischen(User $einer, User $anderer): Unterhaltung
    {
        $vorhanden = Unterhaltung::query()
            ->where('art', Unterhaltungsart::Intern->value)
            ->whereHas('teilnehmer', fn ($q) => $q->whereKey($einer->getKey()))
            ->whereHas('teilnehmer', fn ($q) => $q->whereKey($anderer->getKey()))
            // Genau zwei Beteiligte: ohne diese Bedingung träfe eine spätere
            // Gruppenunterhaltung, in der beide vorkommen, ebenfalls zu.
            ->has('teilnehmer', '=', 2)
            ->first();

        if ($vorhanden !== null) {
            return $vorhanden;
        }

        $unterhaltung = Unterhaltung::query()->create(['art' => Unterhaltungsart::Intern]);

        $unterhaltung->teilnehmer()->syncWithoutDetaching([
            $einer->getKey() => [],
            $anderer->getKey() => [],
        ]);

        return $unterhaltung;
    }

    /**
     * Die Liste, wie sie jemand zu sehen bekommt — neueste zuerst.
     *
     * @return Collection<int, Unterhaltung>
     */
    public static function fuer(User $nutzer): Collection
    {
        return Unterhaltung::query()
            ->sichtbarFuer($nutzer)
            ->begonnen()
            ->with(['customer', 'teilnehmer'])
            ->orderByDesc('letzte_nachricht_am')
            // Zweiter Schlüssel, damit die Reihenfolge feststeht. Die
            // Zeitstempel haben Sekundengenauigkeit (timestamp(0)); zwei
            // Nachrichten in derselben Sekunde sind im Alltag selten, aber
            // dann entscheidet ohne diese Zeile Postgres, welcher Faden oben
            // steht — und beim nächsten Aufruf womöglich anders. Sichtbar
            // wurde es an einem Test, der auf einer Maschine durchlief und
            // auf der anderen nicht; in der Oberfläche wäre es eine Liste,
            // die beim Neuladen springt.
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Wie viele Nachrichten insgesamt ungelesen sind — die Zahl am Menüpunkt.
     *
     * Ohne sie hat der Chat dasselbe Problem wie eine Liste ohne
     * Benachrichtigung: man müsste hineinsehen, um zu wissen, ob man
     * hineinsehen muss.
     */
    public static function ungelesen(?User $nutzer = null): int
    {
        $nutzer ??= auth()->user();

        if ($nutzer === null) {
            return 0;
        }

        // Eine Abfrage und nicht die Summe über fuer() — die Zahl steht in der
        // Navigation und wird damit bei jedem Seitenaufruf gebraucht, auch auf
        // Seiten, die mit Nachrichten nichts zu tun haben. Eine Abfrage je
        // Unterhaltung wäre dort ein Preis, den man nirgends sieht und der
        // mit jedem Kunden wächst.
        return Nachricht::query()
            ->where('nachrichten.user_id', '!=', $nutzer->getKey())
            ->whereHas('unterhaltung', fn (Builder $q) => $q->sichtbarFuer($nutzer))
            ->leftJoin('unterhaltung_teilnehmer as lesestand', function (JoinClause $join) use ($nutzer) {
                $join->on('lesestand.unterhaltung_id', '=', 'nachrichten.unterhaltung_id')
                    ->where('lesestand.user_id', '=', $nutzer->getKey());
            })
            // Noch nie geöffnet zählt als "alles ungelesen".
            ->where(fn (Builder $q) => $q
                ->whereNull('lesestand.gelesen_bis')
                ->orWhereColumn('nachrichten.created_at', '>', 'lesestand.gelesen_bis'))
            ->count();
    }
}
