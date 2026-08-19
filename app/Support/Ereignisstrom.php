<?php

namespace App\Support;

use App\Enums\DokumentStand;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Dokument;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as BuilderVertrag;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Was im Team passiert ist — über alle Tickets hinweg.
 *
 * Der Strom wird aus dem gelesen, was ohnehin schon in der Datenbank steht:
 * Protokoll, Kommentare, Zeiten, Anhänge. Genau deshalb zeigt das Dashboard
 * vom ersten Tag an auch die Vergangenheit und nicht bloß, was ab jetzt
 * geschieht. Eine eigene "Ereignis"-Tabelle, die von nun an mitschreibt,
 * hätte am Einführungstag leer dagestanden.
 *
 * Die Quellen werden per UNION ALL zusammengeführt und erst danach sortiert
 * und begrenzt. Das ist der Grund, warum hier mit dem Query Builder
 * gearbeitet wird und nicht mit Eloquent: fünf Modelle einzeln zu laden,
 * zusammenzuwerfen und in PHP zu sortieren, würde bei "zeige die letzten 20"
 * jedes Mal alles laden.
 */
class Ereignisstrom
{
    /** Umfang: alles, was sichtbar ist. */
    public const ALLES = 'alles';

    /** Umfang: nur Tickets, die mir zugewiesen sind. */
    public const MEINE = 'meine';

    /** Umfang: nur, was andere getan haben. */
    public const ANDERE = 'andere';

    /**
     * Die letzten Ereignisse für diesen Nutzer.
     *
     * @param  string  $typ  einer der Ereignis-Typen oder 'alles'
     * @return Collection<int, Ereignis>
     */
    public static function fuer(
        User $nutzer,
        int $anzahl = 20,
        string $umfang = self::ALLES,
        string $typ = 'alles',
    ): Collection {
        $roh = self::rohdaten($nutzer, $anzahl, $umfang, $typ);

        if ($roh->isEmpty()) {
            return collect();
        }

        return self::ausbauen($roh);
    }

    /**
     * Wie viele Ereignisse es seit einem Zeitpunkt gab.
     *
     * Zählt auf denselben Quellen wie der Strom selbst — sonst stünde in der
     * Überschrift eine Zahl, die zu den Zeilen darunter nicht passt.
     */
    public static function anzahlSeit(User $nutzer, ?Carbon $seit, string $umfang = self::ALLES): int
    {
        if ($seit === null) {
            return 0;
        }

        $union = self::union($nutzer, $umfang, 'alles', $seit);

        return DB::query()->fromSub($union, 'e')->count();
    }

    /**
     * @return Collection<int, object>
     */
    private static function rohdaten(User $nutzer, int $anzahl, string $umfang, string $typ): Collection
    {
        $union = self::union($nutzer, $umfang, $typ);

        return DB::query()
            ->fromSub($union, 'e')
            ->orderByDesc('zeitpunkt')
            // Zweites Kriterium, damit Einträge aus derselben Sekunde eine
            // feste Reihenfolge haben. Ohne das kann dieselbe Liste bei zwei
            // Aufrufen unterschiedlich sortiert sein, und beim "Mehr laden"
            // erscheint ein Eintrag doppelt oder fällt heraus.
            ->orderByDesc('quelle_id')
            ->limit($anzahl)
            ->get();
    }

    /**
     * Die Quellen als eine Abfrage.
     */
    private static function union(User $nutzer, string $umfang, string $typ, ?Carbon $seit = null): Builder
    {
        $teile = [];

        if (self::gewuenscht($typ, Ereignis::AENDERUNG)) {
            // Im Protokoll heißt die Urheberspalte causer_id. Der Alias aus
            // dem SELECT gilt in WHERE noch nicht — deshalb muss der echte
            // Spaltenname durchgereicht werden.
            $teile[] = self::grundgeruest($nutzer, $umfang, $seit, 'causer_id')
                ->from('activity_log')
                ->selectRaw("'".Ereignis::AENDERUNG."' as typ, id as quelle_id, subject_id as ticket_id, causer_id as user_id, created_at as zeitpunkt")
                ->where('subject_type', Ticket::class)
                ->whereIn('subject_id', self::sichtbareTickets($nutzer, $umfang));
        }

        if (self::gewuenscht($typ, Ereignis::KOMMENTAR)) {
            $teile[] = self::grundgeruest($nutzer, $umfang, $seit)
                ->from('comments')
                ->selectRaw("'".Ereignis::KOMMENTAR."' as typ, id as quelle_id, ticket_id, user_id, created_at as zeitpunkt")
                ->whereIn('ticket_id', self::sichtbareTickets($nutzer, $umfang));
        }

        if (self::gewuenscht($typ, Ereignis::ZEIT)) {
            $teile[] = self::grundgeruest($nutzer, $umfang, $seit)
                ->from('time_entries')
                ->selectRaw("'".Ereignis::ZEIT."' as typ, id as quelle_id, ticket_id, user_id, created_at as zeitpunkt")
                ->whereIn('ticket_id', self::sichtbareTickets($nutzer, $umfang));
        }

        if (self::gewuenscht($typ, Ereignis::ANHANG)) {
            $teile[] = self::grundgeruest($nutzer, $umfang, $seit)
                ->from('attachments')
                ->selectRaw("'".Ereignis::ANHANG."' as typ, id as quelle_id, ticket_id, user_id, created_at as zeitpunkt")
                ->whereIn('ticket_id', self::sichtbareTickets($nutzer, $umfang));
        }

        // Antworten des Kunden auf ein Angebot. Die einzige Quelle ohne
        // Ticket — und die einzige, deren Zeitpunkt nicht created_at ist:
        // gemeint ist, wann geantwortet wurde, nicht wann wir das Dokument
        // hochgeladen haben.
        //
        // Unter "Meine Tickets" hat sie nichts zu suchen: ein Angebot ist
        // niemandem zugewiesen, und der Umfang fragt genau danach. Unter
        // "Von anderen" gehört sie dagegen immer — der Urheber ist der Kunde,
        // also nie man selbst.
        if (self::gewuenscht($typ, Ereignis::DOKUMENT) && $umfang !== self::MEINE) {
            $teile[] = self::grundgeruest($nutzer, $umfang, $seit, 'beantwortet_von', 'beantwortet_at')
                ->from('dokumente')
                // null::bigint, nicht bloß null: bei UNION ALL bestimmt der
                // erste Teil den Spaltentyp, und Postgres weist eine
                // typlose Null zurück, sobald dieser Teil vorn steht — also
                // genau dann, wenn man nur nach Dokumenten filtert.
                ->selectRaw("'".Ereignis::DOKUMENT."' as typ, id as quelle_id, null::bigint as ticket_id, beantwortet_von as user_id, beantwortet_at as zeitpunkt")
                ->whereNotNull('beantwortet_at')
                ->whereIn('id', self::sichtbareDokumente($nutzer));
        }

        /** @var Builder $union */
        $union = array_shift($teile);

        foreach ($teile as $teil) {
            $union->unionAll($teil);
        }

        return $union;
    }

    /**
     * Die Einschränkungen, die für jede der vier Quellen gleich gelten.
     */
    private static function grundgeruest(
        User $nutzer,
        string $umfang,
        ?Carbon $seit,
        string $urheberSpalte = 'user_id',
        string $zeitSpalte = 'created_at',
    ): Builder {
        $query = DB::query();

        if ($umfang === self::ANDERE) {
            // Ohne Urheber heißt: über die Schnittstelle hereingekommen, etwa
            // eine Mail per n8n. Das ist ausdrücklich "von anderen" — es ist
            // sogar das, worüber man am ehesten Bescheid wissen will.
            $query->where(fn (Builder $q) => $q
                ->whereNull($urheberSpalte)
                ->orWhere($urheberSpalte, '!=', $nutzer->getKey()));
        }

        if ($seit !== null) {
            $query->where($zeitSpalte, '>', $seit);
        }

        return $query;
    }

    /**
     * Auf welche Tickets der Strom überhaupt schauen darf.
     *
     * Dieselbe Regel wie in jeder Liste: Admins sehen alles, alle anderen nur
     * ihre Projekte. Sie steckt hier in einer Unterabfrage statt in einem
     * nachträglichen Filter, damit fremde Ereignisse gar nicht erst geladen
     * werden — sonst wäre "die letzten 20" für einen Mitarbeiter womöglich
     * eine Liste mit zwei sichtbaren Zeilen.
     */
    private static function sichtbareTickets(User $nutzer, string $umfang): BuilderVertrag
    {
        $tickets = Ticket::query()->sichtbarFuer($nutzer);

        if ($umfang === self::MEINE) {
            $tickets->where('assigned_to', $nutzer->getKey());
        }

        return $tickets->select('tickets.id')->toBase();
    }

    /**
     * Auf welche Dokumente der Strom schauen darf.
     *
     * Dieselbe Regel wie überall: der Scope entscheidet, nicht diese Klasse.
     * Für einen Mitarbeiter sind das die Dokumente seiner Kunden — ein
     * Angebot über fünfstellige Beträge bei einem fremden Kunden geht ihn
     * nichts an, auch nicht als Zeile im Ticker.
     */
    private static function sichtbareDokumente(User $nutzer): BuilderVertrag
    {
        return Dokument::query()
            ->sichtbarFuer($nutzer)
            ->select('dokumente.id')
            ->toBase();
    }

    private static function gewuenscht(string $gewaehlt, string $typ): bool
    {
        return $gewaehlt === 'alles' || $gewaehlt === $typ;
    }

    /**
     * Aus den Rohzeilen fertige Ereignisse machen.
     *
     * Alles wird gebündelt nachgeladen: ein Zugriff je Quelle, einer für die
     * Tickets, einer für die Personen. Die naheliegende Schleife mit
     * Ticket::find() in jeder Runde wären bei zwanzig Zeilen achtzig
     * Abfragen.
     *
     * @param  Collection<int, object>  $roh
     * @return Collection<int, Ereignis>
     */
    private static function ausbauen(Collection $roh): Collection
    {
        $ids = fn (string $typ) => $roh->where('typ', $typ)->pluck('quelle_id')->all();

        $tickets = Ticket::query()
            ->with(['customer', 'project', 'status'])
            ->whereIn('id', $roh->pluck('ticket_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $personen = User::query()
            ->whereIn('id', $roh->pluck('user_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $quellen = [
            Ereignis::AENDERUNG => Activity::query()->whereIn('id', $ids(Ereignis::AENDERUNG))->get()->keyBy('id'),
            Ereignis::KOMMENTAR => Comment::query()->whereIn('id', $ids(Ereignis::KOMMENTAR))->get()->keyBy('id'),
            Ereignis::ZEIT => TimeEntry::query()->whereIn('id', $ids(Ereignis::ZEIT))->get()->keyBy('id'),
            Ereignis::ANHANG => Attachment::query()->whereIn('id', $ids(Ereignis::ANHANG))->get()->keyBy('id'),
            Ereignis::DOKUMENT => Dokument::query()->with('customer')->whereIn('id', $ids(Ereignis::DOKUMENT))->get()->keyBy('id'),
        ];

        return $roh
            ->map(function (object $zeile) use ($tickets, $personen, $quellen): ?Ereignis {
                $quelle = $quellen[$zeile->typ][$zeile->quelle_id] ?? null;

                if ($quelle === null) {
                    return null;
                }

                return self::bauen(
                    $zeile,
                    $quelle,
                    $tickets[$zeile->ticket_id] ?? null,
                    $zeile->user_id ? ($personen[$zeile->user_id] ?? null) : null,
                );
            })
            ->filter()
            ->values();
    }

    private static function bauen(object $zeile, mixed $quelle, ?Ticket $ticket, ?User $nutzer): Ereignis
    {
        $zeitpunkt = Carbon::parse($zeile->zeitpunkt);

        return match ($zeile->typ) {
            Ereignis::AENDERUNG => new Ereignis(
                typ: $quelle->event === 'created' ? Ereignis::ANGELEGT : Ereignis::AENDERUNG,
                zeitpunkt: $zeitpunkt,
                ticket: $ticket,
                nutzer: $nutzer,
                was: $quelle->event === 'created' ? 'hat das Ticket angelegt' : 'hat das Ticket geändert',
                zeilen: $quelle->event === 'created' ? [] : Verlaufstext::zeilen($quelle),
            ),

            Ereignis::KOMMENTAR => new Ereignis(
                typ: Ereignis::KOMMENTAR,
                zeitpunkt: $zeitpunkt,
                ticket: $ticket,
                nutzer: $nutzer,
                was: 'hat kommentiert',
                // Gekürzt, weil der Strom in einer halben Bildschirmbreite
                // steht: ein ungekürzter langer Kommentar schöbe alles
                // Folgende aus dem sichtbaren Bereich. Ganz steht er im
                // Ticket.
                zitat: Str::limit((string) $quelle->body, 280),
                intern: (bool) $quelle->ist_intern,
            ),

            Ereignis::ZEIT => new Ereignis(
                typ: Ereignis::ZEIT,
                zeitpunkt: $zeitpunkt,
                ticket: $ticket,
                nutzer: $nutzer,
                was: $quelle->laeuft()
                    ? 'hat die Uhr gestartet'
                    : 'hat Zeit erfasst: '.self::alsStunden((int) $quelle->minuten),
                zeilen: array_filter([$quelle->beschreibung]),
            ),

            Ereignis::DOKUMENT => new Ereignis(
                typ: Ereignis::DOKUMENT,
                zeitpunkt: $zeitpunkt,
                ticket: null,
                nutzer: $nutzer,
                was: $quelle->stand === DokumentStand::Angenommen
                    ? 'hat das Angebot angenommen'
                    : 'hat das Angebot abgelehnt',
                zeilen: array_filter([$quelle->nummer]),
                kontext: trim($quelle->customer?->name.' — '.$quelle->titel
                    .($quelle->betragLesbar() ? ' ('.$quelle->betragLesbar().')' : '')),
                kontextUrl: $quelle->customer
                    ? CustomerResource::getUrl('view', ['record' => $quelle->customer], panel: 'admin')
                    : null,
            ),

            default => new Ereignis(
                typ: Ereignis::ANHANG,
                zeitpunkt: $zeitpunkt,
                ticket: $ticket,
                nutzer: $nutzer,
                was: 'hat eine Datei angehängt',
                zeilen: [$quelle->dateiname.' ('.$quelle->groesseLesbar().')'],
            ),
        };
    }

    private static function alsStunden(int $minuten): string
    {
        if ($minuten < 60) {
            return $minuten.' min';
        }

        return intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
    }
}
