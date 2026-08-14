<?php

namespace App\Models;

use App\Enums\Rolle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * Mitarbeiter, die diesem Kunden als Ganzes zugeordnet sind.
     *
     * Sie sehen alle Projekte des Kunden — auch die, die erst später
     * entstehen. Das ist der Unterschied zur Zuordnung einzelner Projekte.
     */
    public function mitarbeiter(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Die Kundenzugänge zu diesem Kunden.
     *
     * Mehrere sind ausdrücklich vorgesehen: bei einem Verein sind das der
     * Vorstand und die Person, die die Website tatsächlich betreut, und die
     * sollen sich keinen Zugang teilen — sonst steht unter jedem Anliegen
     * derselbe Name und man weiß nie, mit wem man gerade schreibt.
     *
     * Die Einschränkung auf die Rolle ist wichtig: users.customer_id sagt
     * für sich genommen nur "gehört zu diesem Kunden", und ohne sie stünde
     * ein versehentlich zugeordneter Mitarbeiter in dieser Liste.
     */
    public function zugaenge(): HasMany
    {
        return $this->hasMany(User::class)->where('rolle', Rolle::Kunde->value);
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

        // Ein Kundenzugang kennt genau einen Kunden: seinen eigenen.
        if ($nutzer->istKunde()) {
            return $query->whereKey($nutzer->customer_id);
        }

        // Zwei Wege: direkt dem Kunden zugeordnet, oder einem seiner
        // Projekte. Einer von beiden genügt.
        return $query->where(fn (Builder $q) => $q
            ->whereHas('mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey()))
            ->orWhereHas('projects.mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey())));
    }
}
