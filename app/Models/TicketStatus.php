<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'farbe', 'sortierung', 'ist_abschluss', 'wartet_auf_kunde'])]
class TicketStatus extends Model
{
    use HasFactory;

    /** Der Slug des Stadiums "In Arbeit" — siehe inArbeit(). */
    public const IN_ARBEIT = 'in-arbeit';

    /** Der Slug des Stadiums "Offen" — siehe offen(). */
    public const OFFEN = 'offen';

    protected $attributes = [
        'ist_abschluss' => false,
        'wartet_auf_kunde' => false,
        'farbe' => '#9ca3af',
        'sortierung' => 0,
    ];

    protected function casts(): array
    {
        return [
            'ist_abschluss' => 'boolean',
            'wartet_auf_kunde' => 'boolean',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeSortiert(Builder $query): Builder
    {
        return $query->orderBy('sortierung')->orderBy('id');
    }

    /** Das Stadium, in dem neue Tickets landen: das erste in der Reihenfolge. */
    public static function standard(): ?self
    {
        return static::query()->sortiert()->first();
    }

    /**
     * Das Stadium, in das ein Ticket rutscht, sobald jemand die Uhr startet.
     *
     * Über den Slug und nicht über den Namen: der Name darf umbenannt werden
     * (der Seeder legt ihn nur einmal an), der Slug ist die Kennung. Wer ihn
     * ändert, nimmt dem System diesen Griff — deshalb steht er als Konstante
     * hier und nicht als Zeichenkette irgendwo im Ablauf.
     *
     * Kann null sein: gelöscht ist gelöscht. Wer das Stadium wegräumt, will
     * es offensichtlich nicht mehr, und dann soll nichts dorthin verschoben
     * werden — siehe Observers\TimeEntryObserver.
     */
    public static function inArbeit(): ?self
    {
        return static::query()->where('slug', self::IN_ARBEIT)->first();
    }

    /**
     * Das Stadium für alles, was bei uns liegt, ohne dass jemand daran sitzt.
     *
     * Dorthin kommt ein Ticket zurück, dessen Kunde geantwortet hat und für
     * das niemand eingetragen ist (Support\Automatik). Wie bei inArbeit():
     * über den Slug, und null heißt "gibt es nicht mehr, dann nichts tun".
     */
    public static function offen(): ?self
    {
        return static::query()->where('slug', self::OFFEN)->first();
    }
}
