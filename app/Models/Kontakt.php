<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ein Ansprechpartner beim Kunden.
 *
 * Unabhängig davon, ob die Person einen Zugang hat — siehe die Begründung in
 * der Migration. Wer einen hat, ist über zugang() verknüpft.
 */
#[Fillable([
    'customer_id', 'name', 'funktion', 'email',
    'telefon', 'notiz', 'hauptkontakt', 'aktiv',
])]
class Kontakt extends Model
{
    protected $table = 'kontakte';

    protected $attributes = [
        'hauptkontakt' => false,
        'aktiv' => true,
    ];

    protected function casts(): array
    {
        return [
            'hauptkontakt' => 'boolean',
            'aktiv' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Der Kundenzugang dieser Person, falls sie einen hat. */
    public function zugang(): HasOne
    {
        return $this->hasOne(User::class, 'kontakt_id');
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }

    /**
     * Hauptkontakte zuerst, danach alphabetisch.
     *
     * Steht als Scope hier und nicht in jeder Liste einzeln: die Reihenfolge
     * ist überall dieselbe, und ein Kontakt, der im Kundenbereich an anderer
     * Stelle steht als in der Verwaltung, verwirrt bei jeder Rückfrage.
     */
    public function scopeInReihenfolge(Builder $query): Builder
    {
        return $query->orderByDesc('hauptkontakt')->orderBy('name');
    }
}
