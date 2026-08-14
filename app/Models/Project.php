<?php

namespace App\Models;

use App\Enums\ProjektStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'name', 'slug', 'beschreibung',
    'status', 'farbe', 'budget_stunden',
    'demo_url', 'kunden_info', 'kunden_sichtbar',
])]
class Project extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'aktiv',
        'kunden_sichtbar' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjektStatus::class,
            'budget_stunden' => 'decimal:2',
            'kunden_sichtbar' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Wer dieses Projekt sehen darf (Admins brauchen keinen Eintrag). */
    public function mitarbeiter(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** Bisher auf dieses Projekt gebuchte Stunden. */
    public function erfassteStunden(): float
    {
        $minuten = TimeEntry::query()
            ->whereIn('ticket_id', $this->tickets()->select('id'))
            ->sum('minuten');

        return round($minuten / 60, 2);
    }

    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istAdmin()) {
            return $query;
        }

        // Kundenzugänge sehen die Projekte ihres Kunden, und davon nur die
        // freigegebenen. Der Zweig steht bewusst hier und nicht in einem
        // eigenen Scope fürs Kundenpanel: sichtbarFuer ist die Stelle, durch
        // die im ganzen System jede Projektliste läuft. Ein zweiter Scope
        // daneben hieße, dass jede künftige Liste sich merken muss, welchen
        // von beiden sie nehmen soll — und die eine, die es vergisst, zeigt
        // einem Kunden die Projekte aller anderen.
        if ($nutzer->istKunde()) {
            return $query
                ->where('customer_id', $nutzer->customer_id)
                ->where('kunden_sichtbar', true);
        }

        // Sichtbar, wenn der Nutzer diesem Projekt zugeordnet ist ODER
        // seinem Kunden. Die Kundenzuordnung schließt künftige Projekte
        // automatisch ein — dafür gibt es sie.
        return $query->where(fn (Builder $q) => $q
            ->whereHas('mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey()))
            ->orWhereHas('customer.mitarbeiter', fn (Builder $m) => $m->whereKey($nutzer->getKey())));
    }
}
