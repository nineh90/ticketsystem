<?php

namespace App\Models;

use App\Enums\Prioritaet;
use App\Enums\Quelle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'project_id', 'titel', 'beschreibung', 'ticket_status_id', 'prioritaet',
    'assigned_to', 'created_by', 'faellig_am', 'position', 'quelle', 'external_ref',
])]
class Ticket extends Model
{
    use HasFactory, LogsActivity;

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
                'assigned_to',
                'faellig_am',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('ticket');
    }

    protected $attributes = [
        'prioritaet' => 'normal',
        'quelle' => 'manuell',
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'prioritaet' => Prioritaet::class,
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

        return $query->where(fn (Builder $q) => $q
            ->whereHas('project.mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey()))
            ->orWhereHas('customer.mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey())));
    }
}
