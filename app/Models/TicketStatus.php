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
}
