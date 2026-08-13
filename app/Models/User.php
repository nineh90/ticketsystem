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

#[Fillable(['name', 'email', 'password', 'rolle', 'panel_zugang', 'aktiv'])]
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
        ];
    }

    /**
     * Die Schranke vor dem Panel.
     *
     * Ein gültiges Konto allein reicht ausdrücklich nicht — es muss
     * freigegeben und aktiv sein. Kunden bekommen später ein eigenes Panel;
     * ins interne kommen sie auch mit Freigabe nicht.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->panel_zugang || ! $this->aktiv) {
            return false;
        }

        return $this->rolle !== Rolle::Kunde;
    }

    public function istAdmin(): bool
    {
        return $this->rolle === Rolle::Admin;
    }

    /** Projekte, die dieser Nutzer sehen darf. Für Admins ohne Bedeutung. */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /** Nur für die Rolle "kunde" gesetzt — in v1 bei allen leer. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
