<?php

namespace App\Models;

use App\Enums\Rolle;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rolle', 'panel_zugang', 'aktiv', 'customer_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Sicherer Ausgangszustand schon auf Model-Ebene, nicht nur als
     * Spalten-Default: wer per Factory, Seeder oder Tinker einen Nutzer
     * anlegt und panel_zugang vergisst, erzeugt keinen Zugang.
     */
    protected $attributes = [
        'panel_zugang' => false,
        'rolle' => 'mitarbeiter',
        // Muss hier stehen, nicht nur als Spalten-Default: sonst ist aktiv am
        // frisch erzeugten Objekt null, canAccessPanel() wertet das als
        // "deaktiviert" und der eben angelegte Nutzer ist bis zum nächsten
        // Reload ausgesperrt.
        'aktiv' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rolle' => Rolle::class,
            'panel_zugang' => 'boolean',
            'aktiv' => 'boolean',
            'dashboard_gesehen_at' => 'datetime',
            'letzte_anmeldung_at' => 'datetime',
        ];
    }

    /**
     * Die Schranke vor den Panels.
     *
     * Ein gültiges Konto allein reicht ausdrücklich nicht — es muss
     * freigegeben und aktiv sein. Darüber hinaus gehört jede Rolle in genau
     * ein Panel: Kunden ins Kundenpanel, alle anderen ins interne. Die
     * Trennung läuft in beide Richtungen, und das ist Absicht. Dass ein Kunde
     * nicht ins interne Panel darf, ist offensichtlich; dass umgekehrt auch
     * ein Mitarbeiter nichts im Kundenpanel zu suchen hat, ist es weniger —
     * dort sähe er die Oberfläche eines Kunden, aber mit seinen eigenen
     * Rechten, und jede Regel darin wäre ab da doppelt zu prüfen.
     *
     * Zum Ausprobieren meldet man sich mit dem Kundenzugang an; genau dafür
     * gibt es ihn.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->panel_zugang || ! $this->aktiv) {
            return false;
        }

        if ($panel->getId() === self::PANEL_KUNDE) {
            // Ohne Kundenzuordnung wäre der Bereich leer und jede Abfrage
            // darin unbestimmt — dann lieber gar nicht erst hinein.
            return $this->istKunde() && $this->customer_id !== null;
        }

        return ! $this->istKunde();
    }

    /** Die Kennung des Kundenpanels (siehe KundePanelProvider). */
    public const PANEL_KUNDE = 'kunde';

    public function istAdmin(): bool
    {
        return $this->rolle === Rolle::Admin;
    }

    public function istKunde(): bool
    {
        return $this->rolle === Rolle::Kunde;
    }

    /** Projekte, die dieser Nutzer sehen darf. Für Admins ohne Bedeutung. */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * Der Kunde, zu dem dieser Zugang gehört. Nur bei der Rolle "kunde"
     * gesetzt — er entscheidet im Kundenbereich über alles, was zu sehen ist.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Kunden, die diesem Mitarbeiter als Ganzes zugeordnet sind.
     *
     * Nicht zu verwechseln mit customer(): das ist die Kundenzugehörigkeit
     * eines späteren Kundenzugangs, dies hier die Zuständigkeit eines
     * Mitarbeiters.
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withTimestamps();
    }

    public function zugewieseneTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Die gerade laufende Zeitbuchung, falls es eine gibt. */
    public function laufendeZeit(): ?TimeEntry
    {
        return $this->timeEntries()->laufend()->latest('gestartet_am')->first();
    }
}
