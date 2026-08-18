<?php

namespace App\Models;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Support\Dateigroesse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Ein Angebot, eine Rechnung, ein Vertrag — als fertige Datei am Kunden.
 *
 * Nah an Attachment gebaut und trotzdem ein eigenes Modell: ein Anhang hängt
 * an einem Ticket und ist Beiwerk zu einem Vorgang, ein Dokument hängt am
 * Kunden und ist selbst der Vorgang. Es trägt einen Betrag, einen Stand und
 * eine Freigabe — an einem Anhang wäre jedes dieser Felder für den
 * Normalfall überflüssig.
 *
 * Die Freigabe (kunden_sichtbar) ist die einzige Stelle, die entscheidet, ob
 * der Kunde die Datei bekommt. Sie steht sowohl in sichtbarFuer() als auch
 * in der DokumentPolicy — doppelt, weil die eine Liste füllt und die andere
 * die Ausliefer-Route bewacht.
 */
#[Fillable([
    'customer_id', 'project_id', 'user_id', 'art', 'titel', 'nummer',
    'datum', 'faellig_am', 'betrag', 'stand', 'notiz', 'kunden_sichtbar',
    'pfad', 'dateiname', 'mime', 'groesse',
])]
class Dokument extends Model
{
    use HasFactory;

    protected $table = 'dokumente';

    /** Dieselbe Platte wie die Ticket-Anhänge — bewusst nicht "public". */
    public const PLATTE = 'local';

    /**
     * Der sichere Ausgangszustand am Objekt und nicht nur als Spaltenvorgabe.
     * Wer über Tinker, einen Seeder oder eine spätere Schnittstelle ein
     * Dokument anlegt und den Schalter vergisst, legt keines an, das beim
     * Kunden steht.
     */
    protected $attributes = [
        'kunden_sichtbar' => false,
        'groesse' => 0,
    ];

    protected function casts(): array
    {
        return [
            'art' => DokumentArt::class,
            'stand' => DokumentStand::class,
            'datum' => 'date',
            'faellig_am' => 'date',
            'betrag' => 'decimal:2',
            'beantwortet_at' => 'datetime',
            'kunden_sichtbar' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Datei mitlöschen, wie beim Anhang. Ohne das sammeln sich verwaiste
        // PDFs an, die niemand mehr zuordnen kann — und in einem Angebot
        // stehen Preise, die nach dem Löschen niemanden mehr angehen.
        static::deleted(function (Dokument $dokument) {
            Storage::disk(self::PLATTE)->delete($dokument->pfad);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function hochgeladenVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Die Zeitbuchungen, die mit dieser Rechnung abgegolten sind.
     *
     * Erlaubt die Frage, die ein Stichdatum nicht beantworten könnte: wofür
     * genau steht dieser Betrag. Ein halbes Jahr später ist das der
     * Unterschied zwischen "da war irgendwas im August" und einer Liste.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Wie viele Minuten dieser Rechnung zugeordnet sind. */
    public function zugeordneteMinuten(): int
    {
        return (int) $this->timeEntries()->sum('minuten');
    }

    /** Welcher Kundenzugang zu-/abgesagt hat. */
    public function beantwortetVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beantwortet_von');
    }

    /**
     * Adresse der geschützten Ausliefer-Route.
     *
     * Wie beim Anhang zwei Pfade für dieselbe Datei: nur beim passenden
     * führt eine abgelaufene Sitzung auch zur richtigen Anmeldeseite.
     */
    public function url(): string
    {
        return auth()->user()?->istKunde()
            ? route('kunde.dokument.zeigen', $this)
            : route('dokument.zeigen', $this);
    }

    public function groesseLesbar(): string
    {
        return Dateigroesse::lesbar((int) $this->groesse);
    }

    /** Der Betrag als "1.190,00 €", oder null wenn keiner hinterlegt ist. */
    public function betragLesbar(): ?string
    {
        if ($this->betrag === null) {
            return null;
        }

        return number_format((float) $this->betrag, 2, ',', '.').' €';
    }

    /** Wartet hier noch etwas — eine Antwort oder eine Zahlung? */
    public function istOffen(): bool
    {
        return $this->stand?->istOffen() ?? false;
    }

    /**
     * Offen und die Frist ist durch.
     *
     * Ohne Frist nie überfällig: eine Rechnung ohne Zahlungsziel ist nicht
     * spät, sie ist unvollständig erfasst — und eine rote Zeile dafür würde
     * man beim dritten Mal wegsehen statt nachtragen.
     */
    public function istUeberfaellig(): bool
    {
        return $this->istOffen()
            && $this->faellig_am !== null
            && $this->faellig_am->isBefore(today());
    }

    /** Darf der Kunde hier gerade zusagen oder ablehnen? */
    public function wartetAufAntwort(): bool
    {
        return $this->art->istEntscheidbar()
            && $this->istOffen()
            && $this->kunden_sichtbar;
    }

    /**
     * Die Antwort des Kunden festhalten.
     *
     * An einer Stelle, weil sie aus zwei Knöpfen kommt und beide dasselbe
     * tun müssen: Stand setzen, Zeitpunkt und Person festhalten. Wer den
     * Zeitstempel vergisst, kann später nicht mehr unterscheiden, ob der
     * Kunde zugesagt oder ob wir das für ihn eingetragen haben.
     */
    public function vomKundenBeantworten(DokumentStand $stand, User $wer): void
    {
        $this->stand = $stand;
        $this->beantwortet_at = now();
        $this->beantwortet_von = $wer->getKey();
        $this->save();
    }

    public function scopeOffen(Builder $query): Builder
    {
        return $query->where('stand', DokumentStand::Offen->value);
    }

    public function scopeRechnungen(Builder $query): Builder
    {
        return $query->where('art', DokumentArt::Rechnung->value);
    }

    /** Nur das, was der Kunde sehen darf. */
    public function scopeFuerKunden(Builder $query): Builder
    {
        return $query->where('kunden_sichtbar', true);
    }

    /**
     * Auf das beschränken, was dieser Nutzer sehen darf.
     *
     * Für den Kundenzugang zwei Bedingungen, und beide sind nötig: sein
     * eigener Kunde UND freigegeben. Die zweite ist die, die man beim
     * Nachbauen einer Liste vergisst — und dann steht der Entwurf des
     * nächsten Angebots in seinem Bereich.
     *
     * Anders als bei Projekten spielt kunden_sichtbar am Projekt hier keine
     * Rolle: ein Dokument gehört immer dem Kunden, auch wenn es zusätzlich
     * einem Projekt zugeordnet ist. Eine Rechnung zu verbergen, weil das
     * Projekt noch nicht freigegeben ist, wäre der Fall, in dem jemand sein
     * Geld schuldet und nicht nachsehen kann wofür.
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istKunde()) {
            return $query
                ->where('customer_id', $nutzer->customer_id)
                ->fuerKunden();
        }

        if ($nutzer->istAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'customer',
            fn (Builder $c) => $c->sichtbarFuer($nutzer),
        );
    }
}
