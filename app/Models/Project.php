<?php

namespace App\Models;

use App\Enums\ProjektPhase;
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
    'status', 'phase', 'farbe', 'budget_stunden',
    'demo_url', 'live_url', 'kunden_info', 'kunden_sichtbar',
])]
class Project extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'aktiv',
        'phase' => 'umsetzung',
        'kunden_sichtbar' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjektStatus::class,
            'phase' => ProjektPhase::class,
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

    public function meilensteine(): HasMany
    {
        return $this->hasMany(Meilenstein::class);
    }

    /** Zugangsdaten, die zu genau diesem Projekt gehören. */
    public function zugangsdaten(): HasMany
    {
        return $this->hasMany(Zugangsdaten::class);
    }

    /**
     * Der Fortschritt in Prozent, gerechnet statt getippt.
     *
     * Nur über die kundensichtbaren Meilensteine: was der Kunde nicht sieht,
     * darf seinen Balken nicht bewegen — sonst springt er von 40 auf 60,
     * ohne dass sich für ihn sichtbar etwas getan hätte.
     *
     * Ohne Meilensteine gibt es null und keine 0. Der Unterschied ist der
     * zwischen "noch nichts geschafft" und "wird hier nicht nachgehalten",
     * und nur beim zweiten darf gar kein Balken erscheinen.
     */
    public function fortschritt(): ?int
    {
        $gesamt = $this->meilensteine()->kundenSichtbar()->count();

        if ($gesamt === 0) {
            return null;
        }

        $erledigt = $this->meilensteine()->kundenSichtbar()->erledigt()->count();

        return (int) round($erledigt / $gesamt * 100);
    }

    /**
     * Die Adresse, die für den Kunden gerade die richtige ist.
     *
     * Ab "live" die eigene Adresse, davor die Vorschau — und wenn die
     * passende fehlt, die andere. Ein Kunde soll nicht vor einer Seite ohne
     * Link stehen, nur weil das Projekt eine Phase weiter ist als die Pflege
     * seiner Felder.
     */
    public function aktuelleAdresse(): ?string
    {
        return $this->phase->istVeroeffentlicht()
            ? ($this->live_url ?: $this->demo_url)
            : ($this->demo_url ?: $this->live_url);
    }

    /**
     * Die Vorschau-Adresse, die sich für ein Projekt mit diesem Kürzel
     * anbietet — der Wert hinter dem Knopf neben dem Feld "Vorschau".
     *
     * Ein Muster aus config/demo.php, für alle dasselbe, weil die Vorschauen
     * auf unserem Server liegen. Bewusst kein gepflegtes Feld mehr: das gab
     * es einen Abend lang am Kunden und wurde prompt für die Adresse selbst
     * gehalten — was ein Feld namens "Demo-Adresse" auch verdient hat.
     *
     * Statisch, weil der Vorschlag schon beim Anlegen gebraucht wird, wenn
     * es das Projekt noch gar nicht gibt.
     */
    public static function vorschauVorschlag(?string $slug): ?string
    {
        $muster = trim((string) config('demo.muster'));

        if (blank($muster) || blank($slug)) {
            return null;
        }

        $adresse = str_replace('{projekt}', $slug, $muster);

        if (! preg_match('#^https?://#', $adresse)) {
            $adresse = 'https://'.$adresse;
        }

        return rtrim($adresse, '/');
    }

    /**
     * Führt der Knopf auf die echte Adresse oder auf eine Vorschau?
     *
     * Die Frage hängt an der Adresse, die aktuelleAdresse() tatsächlich
     * liefert — nicht an der Phase. Der Unterschied wird bei einem Projekt
     * sichtbar, das direkt auf der eigenen Domain des Kunden entsteht: dort
     * gibt es gar keine Vorschau, die Phase steht auf "Umsetzung", und der
     * Knopf zeigt trotzdem auf die richtige Seite. Nach der Phase gefragt,
     * hieße er dann "Vorschau ansehen" und meinte die Live-Seite.
     */
    public function zeigtLiveAdresse(): bool
    {
        return filled($this->live_url) && $this->aktuelleAdresse() === $this->live_url;
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
