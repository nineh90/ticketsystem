<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'kuerzel', 'farbe', 'ansprechpartner',
    'email', 'telefon', 'notizen', 'aktiv',
])]
class Customer extends Model
{
    use HasFactory;

    protected $attributes = [
        'aktiv' => true,
        'farbe' => '#00bcd4',
        'ticket_zaehler' => 0,
    ];

    protected function casts(): array
    {
        return [
            'aktiv' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Kürzel immer in Großbuchstaben — LDX, nicht ldx. Sonst stünden in
        // den Ticketnummern beide Schreibweisen nebeneinander.
        static::saving(function (Customer $customer) {
            if ($customer->kuerzel !== null) {
                $customer->kuerzel = mb_strtoupper($customer->kuerzel);
            }
        });
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }

    /**
     * Kunden, mit denen dieser Nutzer zu tun hat.
     *
     * Für Mitarbeiter sind das nur die, bei denen ihnen ein Projekt
     * zugeordnet ist — sonst stünden fremde Kundennamen in Auswahllisten
     * und Zählern.
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'projects.mitarbeiter',
            fn (Builder $q) => $q->whereKey($nutzer->getKey()),
        );
    }
}
