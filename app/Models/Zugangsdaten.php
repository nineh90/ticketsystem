<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Eintrag im Zugangsdaten-Tresor.
 *
 * Der Klassenname steht in der Mehrzahl, weil "ein Zugangsdatum" niemand
 * sagt. Nicht zu verwechseln mit Customer::zugaenge() — das sind die
 * Anmeldekonten der Kunden zu diesem Panel. Hier liegen fremde Zugänge:
 * WordPress, SFTP, DNS, die Basic-Auth der Vorschau.
 *
 * Das Passwort ist verschlüsselt abgelegt (Cast "encrypted", also über den
 * APP_KEY). Es ist damit weder such- noch sortierbar, und ein Wechsel des
 * APP_KEY macht alle Einträge unlesbar — beides bewusst in Kauf genommen.
 */
#[Fillable([
    'customer_id', 'project_id', 'bezeichnung', 'url',
    'benutzername', 'passwort', 'hinweis', 'kunden_sichtbar', 'sortierung',
])]
class Zugangsdaten extends Model
{
    protected $table = 'zugangsdaten';

    /**
     * Der sichere Ausgangszustand schon am Objekt, nicht nur als
     * Spalten-Vorgabe: wer über Tinker, Seeder oder eine spätere
     * Schnittstelle einen Eintrag anlegt und den Schalter vergisst, legt
     * keinen an, den der Kunde sieht.
     */
    protected $attributes = [
        'kunden_sichtbar' => false,
        'sortierung' => 0,
    ];

    protected function casts(): array
    {
        return [
            'passwort' => 'encrypted',
            'kunden_sichtbar' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Wer welche Zugangsdaten sehen darf.
     *
     * Wie überall in diesem System läuft jede Liste durch diesen Scope. Für
     * einen Kunden gelten drei Bedingungen gleichzeitig, und die dritte ist
     * die, die man vergisst: ein Zugang darf zu einem Projekt gehören, das
     * für ihn gesperrt ist. Ohne sie zeigte der Tresor den Login zu einer
     * Vorschau, die er auf keiner anderen Seite zu sehen bekommt.
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        if ($nutzer->istAdmin()) {
            return $query;
        }

        if ($nutzer->istKunde()) {
            return $query
                ->where('customer_id', $nutzer->customer_id)
                ->where('kunden_sichtbar', true)
                ->where(fn (Builder $q) => $q
                    ->whereNull('project_id')
                    ->orWhereHas('project', fn (Builder $p) => $p->where('kunden_sichtbar', true)));
        }

        // Mitarbeiter: dieselbe Regel wie für die Kunden, zu denen sie
        // gehören. Wer einen Kunden nicht sieht, sieht seine Passwörter erst
        // recht nicht.
        return $query->whereHas('customer', fn (Builder $c) => $c->sichtbarFuer($nutzer));
    }

    public function scopeFuerKunden(Builder $query): Builder
    {
        return $query->where('kunden_sichtbar', true);
    }

    public function scopeInReihenfolge(Builder $query): Builder
    {
        return $query->orderBy('sortierung')->orderBy('bezeichnung');
    }

    /**
     * Ob überhaupt etwas zum Anmelden dasteht.
     *
     * Ein Eintrag kann aus reiner Notiz bestehen ("Zugang liegt bei Ali") —
     * dann soll im Kundenbereich kein leeres Passwortfeld mit Kopierknopf
     * erscheinen.
     */
    public function hatAnmeldedaten(): bool
    {
        return filled($this->benutzername) || filled($this->passwort);
    }
}
