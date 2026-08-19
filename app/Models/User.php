<?php

namespace App\Models;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rolle', 'panel_zugang', 'aktiv', 'customer_id', 'kontakt_id', 'stammdaten_pflegen', 'mail_benachrichtigungen', 'mail_ereignisse'])]
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
            'mail_benachrichtigungen' => 'boolean',
            'mail_ereignisse' => 'array',
            'passwort_wechseln' => 'boolean',
            'stammdaten_pflegen' => 'boolean',
            'dashboard_gesehen_at' => 'datetime',
            'letzte_anmeldung_at' => 'datetime',
        ];
    }

    /**
     * Wer ein Passwort geschenkt bekommt, muss es wechseln.
     *
     * Die Regel steht hier und nicht in den drei Formularen, die Passwörter
     * setzen. Das ist der Punkt: es gibt heute drei Stellen (Zugang anlegen,
     * Zugang bearbeiten, "Passwort neu setzen") und morgen eine vierte, und
     * die vierte ist die, die es vergisst. Am Model kommt keine daran vorbei.
     *
     * Die Unterscheidung ist "wer tippt": ändert jemand sein eigenes
     * Passwort, ist das Kennzeichen erledigt. Ändert es ein anderer, gilt es
     * wieder — auch beim fünften Mal, denn auch das fünfte Startpasswort ist
     * durch einen Chatverlauf gegangen.
     *
     * Ohne angemeldeten Nutzer passiert nichts: Seeder, Factories, Konsole
     * und Tests setzen Passwörter, ohne dass ein Mensch etwas geschenkt
     * bekommen hätte.
     */
    protected static function booted(): void
    {
        static::saving(function (User $nutzer) {
            if (! $nutzer->isDirty('password')) {
                return;
            }

            $handelnder = auth()->id();

            if ($handelnder === null) {
                return;
            }

            $nutzer->passwort_wechseln = $handelnder !== $nutzer->getKey();
        });
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

    /**
     * Bekommt dieser Zugang Meldungen zusätzlich per Mail?
     *
     * Drei Bedingungen, und die dritte ist die, um die es hier eigentlich
     * geht: **Kundenzugänge nie**, gleich was am Schalter steht. Ihre
     * Adressen hat niemand bestätigt — sie stammen daher, dass wir sie beim
     * Anlegen eingetippt haben (siehe README, „Adressen und Mail"). Ein
     * versehentlich gesetzter Haken wäre sonst genau der Weg, auf dem der
     * Titel eines Tickets an eine geratene oder geteilte Adresse geht.
     *
     * Die Zeile fällt, wenn der Versand nach außen drankommt — dann aber
     * zusammen mit einer bestätigten Adresse und einer Mail, die nur einen
     * Hinweis trägt und keinen Inhalt. Bis dahin ist sie die Sperre.
     */
    public function bekommtMailMeldungen(?MailEreignis $ereignis = null): bool
    {
        $grundsaetzlich = $this->aktiv
            && ! $this->istKunde()
            && (bool) $this->mail_benachrichtigungen
            && filled($this->email);

        if (! $grundsaetzlich || $ereignis === null) {
            return $grundsaetzlich;
        }

        return $this->willMailZu($ereignis);
    }

    /**
     * Steht dieses Ereignis in seiner Auswahl?
     *
     * **null heißt alles.** Wer die Auswahl nie angefasst hat, bekommt jedes
     * Ereignis — auch solche, die es beim Anlegen seines Zugangs noch nicht
     * gab. Eine beim Einführen festgeschriebene Liste schlösse jeden künftigen
     * Typ stillschweigend aus, und das fiele niemandem auf.
     *
     * Eine leere Liste heißt dagegen wirklich nichts: sie entsteht nur, wenn
     * jemand bewusst alle Haken entfernt hat.
     */
    public function willMailZu(MailEreignis $ereignis): bool
    {
        if ($this->mail_ereignisse === null) {
            return true;
        }

        return in_array($ereignis->value, $this->mail_ereignisse, true);
    }

    public function istKunde(): bool
    {
        return $this->rolle === Rolle::Kunde;
    }

    /**
     * Unser Team: alle, die kein Kundenzugang sind.
     *
     * Für jede Auswahlliste, in der jemand aus dem Haus gemeint ist —
     * Zuständigkeit eines Tickets, Team eines Projekts, Betreuer eines
     * Kunden. Ohne diesen Scope standen dort auch die Kundenzugänge, und
     * zwar mit dem Namen der Kundin: man konnte ein Ticket an die Person
     * zuweisen, die es gemeldet hat.
     *
     * Als Scope und nicht fünfmal als "where" abgeschrieben. Genau das war
     * der Fehler: fünf Auswahllisten, fünfmal nur nach "aktiv" gefiltert,
     * und keiner der fünf Stellen sieht man an, dass sie eine sechste
     * Bedingung braucht.
     *
     * Inaktive sind ausgenommen. Wer ausgeschieden ist, soll nichts Neues
     * mehr bekommen — seine bestehenden Zuordnungen bleiben bestehen.
     */
    public function scopeIntern(Builder $query): Builder
    {
        return $query
            ->where('aktiv', true)
            ->where('rolle', '!=', Rolle::Kunde->value);
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
     * Die Person hinter diesem Kundenzugang, sofern sie als Ansprechpartner
     * hinterlegt ist. Optional — ein Zugang funktioniert auch ohne.
     */
    public function kontakt(): BelongsTo
    {
        return $this->belongsTo(Kontakt::class);
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

    /**
     * Darf dieser Nutzer mit diesem Kunden zu tun haben?
     *
     * Die Frage stellt sich außerhalb von Listen — beim Öffnen einer
     * einzelnen Unterhaltung etwa, wo es keine Abfrage gibt, die man filtern
     * könnte. Sie geht trotzdem durch denselben Scope wie jede Liste: eine
     * zweite, von Hand nachgebaute Fassung der Zuständigkeit wäre genau die,
     * die beim nächsten "Mitarbeiter dürfen jetzt auch …" übersehen wird.
     */
    public function istBerechtigtFuerKunde(int $customerId): bool
    {
        return Customer::query()->whereKey($customerId)->sichtbarFuer($this)->exists();
    }

    public function zugewieseneTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Alle Unterhaltungen, an denen dieser Nutzer als Person beteiligt ist.
     *
     * Nur für die internen Fäden aussagekräftig — an einer Kundenunterhaltung
     * hängt die Zeile am Lesestand, nicht an einer Mitgliedschaft. Siehe
     * Unterhaltung::teilnehmer().
     */
    public function unterhaltungen(): BelongsToMany
    {
        return $this->belongsToMany(Unterhaltung::class, 'unterhaltung_teilnehmer')
            ->withPivot('gelesen_bis')
            ->withTimestamps();
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
