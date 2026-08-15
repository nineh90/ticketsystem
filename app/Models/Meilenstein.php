<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Meilenstein im Projekt — ein Punkt auf dem Zeitstrahl, den der Kunde
 * in seinem Bereich sieht.
 */
#[Fillable([
    'project_id', 'titel', 'beschreibung', 'faellig_am',
    'erledigt_at', 'kunden_sichtbar', 'sortierung',
])]
class Meilenstein extends Model
{
    protected $table = 'meilensteine';

    protected $attributes = [
        'kunden_sichtbar' => true,
        'sortierung' => 0,
    ];

    protected function casts(): array
    {
        return [
            'faellig_am' => 'date',
            'erledigt_at' => 'datetime',
            'kunden_sichtbar' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function istErledigt(): bool
    {
        return $this->erledigt_at !== null;
    }

    /**
     * Überfällig heißt: Termin vorbei und nichts passiert.
     *
     * Wird im Kundenbereich nicht angezeigt — ein rotes Abzeichen an einem
     * Termin, den wir selbst gesetzt haben, ist eine Nachricht an uns, keine
     * an den Kunden. Intern steht es in der Projektverwaltung.
     */
    public function istUeberfaellig(): bool
    {
        return ! $this->istErledigt()
            && $this->faellig_am !== null
            && $this->faellig_am->isPast();
    }

    public function scopeKundenSichtbar(Builder $query): Builder
    {
        return $query->where('kunden_sichtbar', true);
    }

    public function scopeErledigt(Builder $query): Builder
    {
        return $query->whereNotNull('erledigt_at');
    }

    public function scopeOffen(Builder $query): Builder
    {
        return $query->whereNull('erledigt_at');
    }

    public function scopeInReihenfolge(Builder $query): Builder
    {
        return $query->orderBy('sortierung')->orderBy('id');
    }
}
