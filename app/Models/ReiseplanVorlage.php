<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Vorlage für den Reiseplan — Website, App, Betreuung.
 *
 * Die immer gleichen Etappen eines Projekts, damit sie nicht bei jedem Kunden
 * neu getippt werden und, wichtiger, damit sie überall gleich heißen. Zwei
 * Kunden, bei denen dieselbe Etappe einmal "Designvorschlag" und einmal
 * "Entwurf" heißt, lesen dasselbe und verstehen Verschiedenes.
 *
 * **Eine Vorlage ist ein Vorschlag, kein Ablauf.** Sie wird am Projekt über
 * "Aus Vorlage" angewandt, man wählt dabei ab, was nicht passt, und ergänzt
 * danach von Hand. Nichts passiert automatisch beim Anlegen eines Projekts:
 * ein Reiseplan, der ungefragt neun Etappen hat, wird nicht gepflegt,
 * sondern weggeklickt.
 *
 * **Angelegte Etappen sind ab dem Anlegen eigenständig** und ändern sich
 * nicht mit, wenn die Vorlage später anders lautet. Das ist Absicht: eine
 * Vorlage, die rückwirkend Titel in laufenden Kundenprojekten umschreibt,
 * wäre eine böse Überraschung — beim Kunden, der sie liest.
 */
#[Fillable(['name', 'schluessel', 'sortierung', 'ist_vorgabe'])]
class ReiseplanVorlage extends Model
{
    protected $table = 'reiseplan_vorlagen';

    protected $attributes = [
        'ist_vorgabe' => false,
        'sortierung' => 0,
    ];

    protected function casts(): array
    {
        return [
            'ist_vorgabe' => 'boolean',
            'sortierung' => 'integer',
        ];
    }

    /**
     * Es gibt höchstens eine Vorgabe.
     *
     * Am Modell und nicht im Formular: es gibt heute einen Weg, eine Vorlage
     * anzulegen, und morgen vielleicht einen zweiten. Zwei vorausgewählte
     * Vorlagen wären kein Fehler, den man sieht — das Formular nähme
     * stillschweigend die erste.
     */
    protected static function booted(): void
    {
        static::saved(function (self $vorlage): void {
            if (! $vorlage->ist_vorgabe) {
                return;
            }

            static::query()
                ->whereKeyNot($vorlage->getKey())
                ->where('ist_vorgabe', true)
                ->update(['ist_vorgabe' => false]);
        });
    }

    public function punkte(): HasMany
    {
        return $this->hasMany(ReiseplanPunkt::class, 'reiseplan_vorlage_id')
            ->orderBy('sortierung')
            ->orderBy('id');
    }

    public function scopeInReihenfolge(Builder $query): Builder
    {
        return $query->orderBy('sortierung')->orderBy('name');
    }
}
