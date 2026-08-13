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
}
