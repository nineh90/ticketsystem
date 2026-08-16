<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id', 'user_id', 'gestartet_am', 'beendet_am',
    'minuten', 'beschreibung', 'abrechenbar',
])]
class TimeEntry extends Model
{
    use HasFactory;

    /** Ab wann eine noch am selben Tag laufende Uhr auffällt. */
    public const AUFFAELLIG_AB_MINUTEN = 8 * 60;

    protected $attributes = [
        'abrechenbar' => true,
        'minuten' => 0,
    ];

    protected function casts(): array
    {
        return [
            'gestartet_am' => 'datetime',
            'beendet_am' => 'datetime',
            'abrechenbar' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function laeuft(): bool
    {
        return $this->beendet_am === null;
    }

    /**
     * Wie lange diese Buchung bisher läuft, in Minuten.
     *
     * Für laufende Uhren, deren Spalte "minuten" noch auf 0 steht: die wird
     * erst beim Stoppen geschrieben. Bei einer beendeten Buchung gilt der
     * festgeschriebene Wert, denn der kann von Hand korrigiert worden sein.
     */
    public function bisherigeMinuten(): int
    {
        if (! $this->laeuft()) {
            return (int) $this->minuten;
        }

        return max(0, (int) $this->gestartet_am->diffInMinutes(now()));
    }

    /**
     * Läuft diese Uhr auffällig lange?
     *
     * Zwei Fälle, und der zweite ist der eigentliche: eine Uhr, die seit
     * gestern läuft, hat niemand gestoppt. Über die reine Stundenzahl allein
     * wäre der erste Arbeitstag, der einmal neun Stunden dauert, genauso
     * rot wie ein vergessenes Wochenende.
     */
    public function laeuftAuffaelligLange(): bool
    {
        if (! $this->laeuft()) {
            return false;
        }

        return ! $this->gestartet_am->isToday()
            || $this->bisherigeMinuten() >= self::AUFFAELLIG_AB_MINUTEN;
    }

    /**
     * Laufende Buchung beenden und die Dauer festschreiben.
     *
     * diffInMinutes rechnet über Tagesgrenzen und Zeitumstellungen hinweg
     * korrekt, solange die Zeitstempel echte Zeitpunkte sind — deshalb steht
     * APP_TIMEZONE=Europe/Berlin nicht zufällig in der .env.
     */
    public function stoppen(?\DateTimeInterface $zeitpunkt = null): void
    {
        if (! $this->laeuft()) {
            return;
        }

        $ende = $zeitpunkt ? \Illuminate\Support\Carbon::instance($zeitpunkt) : now();

        $this->beendet_am = $ende;
        $this->minuten = max(0, (int) $this->gestartet_am->diffInMinutes($ende));
        $this->save();
    }

    public function scopeLaufend(Builder $query): Builder
    {
        return $query->whereNull('beendet_am');
    }

    /**
     * Auf das beschränken, was dieser Nutzer sehen darf.
     *
     * Dieselbe Regel wie in der Zeitentabelle eines Tickets: wer das Ticket
     * sieht, sieht auch, wer daran wie lange gearbeitet hat. Dazu immer die
     * eigenen Buchungen — sonst verschwände die eigene laufende Uhr aus der
     * Übersicht, sobald einem das Projekt entzogen wird, und genau die wäre
     * dann die, die niemand mehr stoppt.
     *
     * Kunden bekommen hier grundsätzlich nichts, siehe TimeEntryPolicy.
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istKunde()) {
            return $query->whereRaw('1 = 0');
        }

        if ($nutzer->istAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $nutzer->getKey())
            ->orWhereHas('ticket', fn (Builder $t) => $t->sichtbarFuer($nutzer)));
    }
}
