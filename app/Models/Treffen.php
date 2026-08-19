<?php

namespace App\Models;

use App\Observers\TreffenObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Ein Treffen mit einem Kunden.
 *
 * Der Einzahl-Plural ist Absicht: "ein Treffen", "zwei Treffen". Die Tabelle
 * heißt genauso, deshalb steht $table hier ausdrücklich — Laravel würde
 * sonst "treffens" bilden.
 */
#[ObservedBy(TreffenObserver::class)]
#[Fillable([
    'customer_id', 'project_id', 'erstellt_von', 'titel', 'notiz',
    'beginnt_am', 'dauer_minuten', 'url', 'kunden_sichtbar', 'abgesagt_at',
])]
class Treffen extends Model
{
    use HasFactory;

    protected $table = 'treffen';

    /**
     * Der sichere Ausgangszustand am Objekt, nicht nur als Spaltenvorgabe —
     * dieselbe Überlegung wie beim Dokument: wer über Tinker oder eine
     * spätere Schnittstelle ein Treffen anlegt und den Schalter vergisst,
     * lädt niemanden versehentlich ein.
     */
    protected $attributes = [
        'kunden_sichtbar' => false,
        'dauer_minuten' => 30,
    ];

    protected function casts(): array
    {
        return [
            'beginnt_am' => 'datetime',
            'abgesagt_at' => 'datetime',
            'kunden_sichtbar' => 'boolean',
            'dauer_minuten' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function erstellerIn(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    /**
     * Wer von uns dabei ist.
     *
     * Der Kunde steht hier bewusst nicht drin — er hängt über customer_id am
     * Treffen und ist der Grund dafür. Zwei Wahrheiten darüber, wer
     * eingeladen ist, wären eine zu viel.
     */
    public function crew(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'treffen_user')->withTimestamps();
    }

    public function endetAm(): Carbon
    {
        return $this->beginnt_am->copy()->addMinutes($this->dauer_minuten);
    }

    public function istAbgesagt(): bool
    {
        return $this->abgesagt_at !== null;
    }

    /**
     * Läuft das Treffen gerade?
     *
     * Bestimmt beim Kunden, ob der Knopf "An Bord gehen" hervorgehoben ist.
     * Eine Viertelstunde Vorlauf, weil niemand auf die Sekunde erscheint —
     * und weil ein Knopf, der erst um Punkt vierzehn Uhr angeht, genau dann
     * fehlt, wenn jemand pünktlich sein will.
     */
    public function laeuft(): bool
    {
        if ($this->istAbgesagt()) {
            return false;
        }

        return now()->between(
            $this->beginnt_am->copy()->subMinutes(15),
            $this->endetAm(),
        );
    }

    /**
     * Ist das Treffen vorbei?
     *
     * Nach dem Ende, nicht nach dem Beginn: ein Treffen um vierzehn Uhr ist
     * um Viertel nach nicht vergangen, sondern mittendrin.
     */
    public function istVorbei(): bool
    {
        return $this->endetAm()->isPast();
    }

    /** Das nächste zuerst — für alle Listen, die nach vorne schauen. */
    public function scopeAlsNaechstes(Builder $query): Builder
    {
        return $query->orderBy('beginnt_am');
    }

    /**
     * Was noch bevorsteht.
     *
     * Gemessen am Ende und nicht am Beginn, aus demselben Grund wie oben:
     * ein laufendes Treffen soll nicht aus der Liste fallen, während man
     * darin sitzt.
     */
    public function scopeBevorstehend(Builder $query): Builder
    {
        return $query->where(
            fn (Builder $q) => $q->whereRaw(
                "beginnt_am + (dauer_minuten * interval '1 minute') >= ?",
                [now()],
            ),
        );
    }

    public function scopeKundenSichtbar(Builder $query): Builder
    {
        return $query->where('kunden_sichtbar', true);
    }

    public function scopeNichtAbgesagt(Builder $query): Builder
    {
        return $query->whereNull('abgesagt_at');
    }

    /**
     * Wer welches Treffen sieht.
     *
     * Dieselbe Regel wie überall sonst, an genau einer Stelle je Modell —
     * ein Kundenzugang sieht seine freigegebenen Treffen, intern gilt die
     * Sichtbarkeit des Kunden. Ein abgesagtes Treffen bleibt dabei drin: der
     * Kunde soll die Absage sehen und nicht bloß ein verschwundenes Treffen.
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istKunde()) {
            return $query
                ->kundenSichtbar()
                ->where('customer_id', $nutzer->customer_id);
        }

        return $query->whereHas(
            'customer',
            fn (Builder $q) => $q->sichtbarFuer($nutzer),
        );
    }
}
