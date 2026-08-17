<?php

namespace App\Models;

use App\Enums\Prioritaet;
use App\Enums\Quelle;
use App\Enums\TicketArt;
use App\Observers\TicketObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'project_id', 'titel', 'beschreibung', 'art', 'ticket_status_id', 'prioritaet',
    'assigned_to', 'created_by', 'faellig_am', 'position', 'quelle', 'external_ref',
])]
#[ObservedBy(TicketObserver::class)]
class Ticket extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Wie lange ein offenes Ticket ruhen darf, bevor es auffällt.
     *
     * Stand vorher als Konstante im Widget TeamUeberblick. Seit die Kachel
     * dorthin verlinkt, wo dieselben Tickets stehen, muss die Zahl an einer
     * Stelle stehen: Kachel und Liste, die sich auseinanderentwickeln, sind
     * schlimmer als gar keine Verlinkung — man klickt auf eine 4 und findet
     * sieben Zeilen.
     */
    public const RUHEND_AB_TAGEN = 3;

    /** Wie viel vom Titel in die Adresse darf (siehe getRouteKey). */
    private const TITEL_IN_ADRESSE = 60;

    /**
     * Was im Verlauf protokolliert wird.
     *
     * Bewusst nicht die Beschreibung: sie wird beim Schreiben oft mehrfach
     * überarbeitet und würde den Verlauf mit Textwänden zumüllen, in denen
     * die eigentlich interessanten Ereignisse untergehen — wer hat den
     * Status geändert, wer hat zugewiesen, wann wurde es fällig gestellt.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'titel',
                'ticket_status_id',
                'prioritaet',
                'art',
                'assigned_to',
                'faellig_am',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('ticket');
    }

    protected $attributes = [
        'prioritaet' => 'normal',
        'art' => 'aufgabe',
        'quelle' => 'manuell',
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'prioritaet' => Prioritaet::class,
            'art' => TicketArt::class,
            'quelle' => Quelle::class,
            'faellig_am' => 'date',
            'erledigt_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            // customer_id wird nie von Hand gesetzt, sondern immer aus dem
            // Projekt abgeleitet. Sonst könnten die beiden Angaben
            // auseinanderlaufen und die Ticketnummer zeigte auf einen anderen
            // Kunden als das Projekt.
            $ticket->customer_id ??= $ticket->project->customer_id;

            $ticket->nummer ??= static::naechsteNummer($ticket->customer_id);
        });

        // erledigt_at hängt am Stadium, nicht an einem separaten Feld, das
        // jemand zu setzen vergessen könnte.
        static::saving(function (Ticket $ticket) {
            if (! $ticket->isDirty('ticket_status_id')) {
                return;
            }

            // Bewusst eine eigene Abfrage statt $ticket->status: die
            // Beziehung ist zu diesem Zeitpunkt in der Regel schon geladen
            // und liefert dann noch das ALTE Stadium, während
            // ticket_status_id längst auf das neue zeigt. Das Ergebnis wäre
            // ein erledigt_at, das genau verkehrt herum gesetzt wird.
            $abgeschlossen = (bool) TicketStatus::query()
                ->whereKey($ticket->ticket_status_id)
                ->value('ist_abschluss');

            if ($abgeschlossen && $ticket->erledigt_at === null) {
                $ticket->erledigt_at = now();
            }

            if (! $abgeschlossen) {
                $ticket->erledigt_at = null;
            }
        });
    }

    /**
     * Nächste Ticketnummer für einen Kunden.
     *
     * Der Zähler wird per SELECT ... FOR UPDATE gesperrt, bevor er gelesen und
     * erhöht wird. Ohne die Sperre hätten zwei gleichzeitige Anfragen — etwa
     * zwei von n8n in derselben Sekunde eingelieferte Mails — denselben Wert
     * gelesen und dieselbe Nummer vergeben.
     *
     * Die Sperre gilt nur innerhalb einer Transaktion; deshalb wird hier eine
     * eigene eröffnet, falls noch keine läuft.
     */
    public static function naechsteNummer(int $customerId): int
    {
        $vergeben = function () use ($customerId): int {
            $kunde = DB::table('customers')
                ->where('id', $customerId)
                ->lockForUpdate()
                ->first(['ticket_zaehler']);

            if ($kunde === null) {
                throw new \RuntimeException("Kunde {$customerId} existiert nicht.");
            }

            $naechste = $kunde->ticket_zaehler + 1;

            DB::table('customers')
                ->where('id', $customerId)
                ->update(['ticket_zaehler' => $naechste]);

            return $naechste;
        };

        return DB::transactionLevel() > 0
            ? $vergeben()
            : DB::transaction($vergeben);
    }

    /** Die Nummer, wie sie überall angezeigt wird: LDX-42. */
    public function kennung(): string
    {
        return $this->customer->kuerzel.'-'.$this->nummer;
    }

    /**
     * Was in der Adresszeile steht: `dlh-3-allergene-pflegen`.
     *
     * Vorher stand dort die Datenbank-ID — eine Zahl, die in der Oberfläche
     * nirgends vorkommt. Wer einen Link weitergibt oder ein Lesezeichen
     * ansieht, weiß jetzt, worum es geht.
     *
     * Die Kennung steht vorn und der Titel dahinter, und diese Reihenfolge
     * ist die ganze Konstruktion: aufgelöst wird ausschließlich über die
     * Kennung, der Titel ist Beiwerk. Er darf sich ändern, er darf doppelt
     * vorkommen — beides gibt es hier wirklich, "Impressum anpassen" liegt
     * bei zwei Kunden —, ohne dass ein Link darunter kaputtgeht.
     */
    public function getRouteKey(): string
    {
        $kennung = Str::lower($this->customer?->kuerzel.'-'.$this->nummer);

        // Mit 'de', sonst fällt Str::slug die Umlaute einfach weg: aus
        // "Grüße" würde "grusse" statt "gruesse". Auf Deutsch gelesen sieht
        // das nach Tippfehler aus, und Adressen sind das Erste, was man von
        // einem Ticket sieht, bevor man es geöffnet hat.
        $titel = Str::of($this->titel)->slug('-', 'de')->limit(self::TITEL_IN_ADRESSE, '')->trim('-');

        return $titel->isEmpty() ? $kennung : $kennung.'-'.$titel;
    }

    /**
     * Und zurück.
     *
     * Drei Fälle, und der dritte ist der Grund, warum hier überhaupt etwas
     * steht statt einer Slug-Spalte:
     *
     *  - `dlh-3-irgendein-titel` — Kennung vorn, Rest wird weggeworfen.
     *  - `7` — die alte Form. Sie muss gültig bleiben: in den gespeicherten
     *    Benachrichtigungen stehen fertige Adressen mit der ID darin, und
     *    deren "Ansehen"-Knopf soll auch in einem halben Jahr noch etwas
     *    öffnen.
     *  - alles andere — nichts finden, also 404. Ausdrücklich und nicht
     *    dadurch, dass die Abfrage zufällig leer ausgeht.
     *
     * Kürzel sind laut Formular alphanumerisch und zwei bis fünf Zeichen
     * lang, die Nummer ist eine Ziffernfolge — daher lässt sich der Anfang
     * eindeutig abtrennen, ohne dass ein Bindestrich im Titel stört.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $wert = (string) $value;

        if (ctype_digit($wert)) {
            return $query->whereKey($wert);
        }

        if (! preg_match('/^([a-z0-9]{2,5})-(\d+)(?:-|$)/i', $wert, $teile)) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('customer', fn (Builder $q) => $q->whereRaw('lower(kuerzel) = ?', [Str::lower($teile[1])]))
            ->where('nummer', (int) $teile[2]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function zustaendig(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Beliebig viele Anhänge je Ticket, vor allem Screenshots. */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest('id');
    }

    /** Nur die Bilder — für die Vorschau auf der Detailseite. */
    public function bilder(): HasMany
    {
        return $this->attachments()->where('mime', 'like', 'image/%');
    }

    /**
     * Der Verlauf.
     *
     * Von Hand definiert statt über das Trait HasActivity: das würde
     * CausesActivity mitbringen, und ein Ticket ist nie Verursacher einer
     * Änderung — nur ihr Gegenstand.
     */
    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    /** Summe der erfassten Minuten. */
    public function erfassteMinuten(): int
    {
        return (int) $this->timeEntries()->sum('minuten');
    }

    /** Nur Tickets, die nicht in einem abschließenden Stadium stehen. */
    public function scopeOffen(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $q) => $q->where('ist_abschluss', false));
    }

    /**
     * Offen und der Termin liegt in der Vergangenheit.
     *
     * Beide Bedingungen gehören zusammen: ein erledigtes Ticket mit altem
     * Termin ist nicht überfällig, sondern fertig.
     *
     * Stand bis eben an vier Stellen ausgeschrieben — im Reiter, in zwei
     * Dashboard-Kacheln und im Filter. Seit die Kacheln auf die Liste
     * verlinken, ist die Doppelung nicht mehr bloß unschön: eine Zahl, die
     * auf eine Liste mit anderer Definition führt, ist eine Lüge.
     */
    public function scopeUeberfaellig(Builder $query): Builder
    {
        return $query
            ->offen()
            ->whereDate('faellig_am', '<', today());
    }

    /**
     * Offen, seit Tagen unverändert und ohne Wortmeldung.
     *
     * Nur auf updated_at zu schauen reicht nicht — ein Ticket, unter dem
     * heute diskutiert wurde, ist nicht liegen geblieben, auch wenn niemand
     * ein Feld angefasst hat.
     */
    public function scopeRuhend(Builder $query): Builder
    {
        $grenze = now()->subDays(self::RUHEND_AB_TAGEN);

        return $query
            ->offen()
            ->where('updated_at', '<', $grenze)
            ->whereDoesntHave('comments', fn (Builder $q) => $q->where('created_at', '>=', $grenze));
    }

    /** Offen und fällig in einem Zeitraum — von heute bis Sonntag etwa. */
    public function scopeFaelligBis(Builder $query, mixed $bis): Builder
    {
        return $query
            ->offen()
            ->whereBetween('faellig_am', [today(), $bis]);
    }

    /** Was Kunden selbst gemeldet haben. */
    public function scopeVomKunden(Builder $query): Builder
    {
        return $query->where('quelle', Quelle::Kunde);
    }

    /**
     * Tickets, bei denen der Ball beim Kunden liegt.
     *
     * Im Kundenbereich die wichtigste Liste überhaupt: sie beantwortet die
     * einzige Frage, auf die er handeln kann — "muss ich etwas tun?".
     */
    public function scopeWartetAufKunde(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $q) => $q->where('wartet_auf_kunde', true));
    }

    public function wartetAufKunde(): bool
    {
        return (bool) $this->status?->wartet_auf_kunde;
    }

    /** Wurde dieses Ticket von einem Kunden gemeldet? */
    public function istVomKunden(): bool
    {
        return $this->quelle === Quelle::Kunde;
    }

    /**
     * Auf das beschränken, was dieser Nutzer sehen darf.
     *
     * Admins sehen alles. Alle anderen nur Tickets aus Projekten, denen sie
     * zugeordnet sind — das wird hier in der Abfrage erledigt und nicht erst
     * beim Öffnen geprüft, damit fremde Tickets gar nicht erst in Listen,
     * Zählern oder Suchergebnissen auftauchen.
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istAdmin()) {
            return $query;
        }

        // Der Kunde sieht alle Tickets seiner freigegebenen Projekte, nicht
        // nur die selbst gemeldeten — das ist der Sinn der Sache: er soll
        // sehen, woran gearbeitet wird, ohne zu fragen. Was er dabei NICHT
        // sieht, hängt nicht an dieser Abfrage, sondern an den internen
        // Kommentaren (Comment::scopeFuerKunden) und daran, dass es im
        // Kundenpanel keine Zeitbuchungen gibt.
        //
        // Die Bedingung läuft über die Projektbeziehung und nicht über
        // tickets.customer_id, weil sonst Tickets aus einem verborgenen
        // Projekt durchkämen: sie tragen dieselbe customer_id.
        if ($nutzer->istKunde()) {
            return $query->whereHas(
                'project',
                fn (Builder $p) => $p
                    ->where('customer_id', $nutzer->customer_id)
                    ->where('kunden_sichtbar', true),
            );
        }

        return $query->where(fn (Builder $q) => $q
            ->whereHas('project.mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey()))
            ->orWhereHas('customer.mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey())));
    }
}
