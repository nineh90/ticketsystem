<?php

namespace App\Models;

use App\Enums\Unterhaltungsart;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ein laufender Faden — mit einem Kunden oder mit einem Kollegen.
 *
 * Die ganze Feinheit dieses Modells steckt darin, wer mitliest, und das ist
 * je Art eine andere Frage:
 *
 *   Kundenunterhaltung — der Faden gehört dem Kunden. Auf seiner Seite lesen
 *   alle seine Zugänge, auf unserer alle Zuständigen. Wer zuständig ist, sagt
 *   Customer::sichtbarFuer, also dieselbe Regel wie bei Tickets und Zeiten.
 *   Damit gibt es keine zweite Rechteregel neben der bestehenden, die man bei
 *   der nächsten Zuordnung getrennt nachziehen müsste.
 *
 *   Interne Unterhaltung — der Faden gehört den beiden Beteiligten. Hier
 *   zählt ausschließlich die Teilnehmerliste, und zwar auch für
 *   Administratoren.
 *
 * @property-read Carbon|null $letzte_nachricht_am
 */
#[Fillable(['art', 'customer_id', 'letzte_nachricht_am'])]
class Unterhaltung extends Model
{
    use HasFactory;

    protected $table = 'unterhaltungen';

    protected function casts(): array
    {
        return [
            'art' => Unterhaltungsart::class,
            'letzte_nachricht_am' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Nachricht, $this> */
    public function nachrichten(): HasMany
    {
        return $this->hasMany(Nachricht::class)->oldest();
    }

    /**
     * Die Beteiligten — mit ihrem Lesestand.
     *
     * Bei einer internen Unterhaltung ist diese Liste die Mitgliedschaft: wer
     * hier nicht steht, sieht den Faden nicht. Bei einer Kundenunterhaltung
     * sagt eine Zeile nur, wie weit derjenige gelesen hat; sie entsteht beim
     * ersten Öffnen. Wer dort mitlesen darf, ergibt sich aus der
     * Kundenzuordnung und nicht aus dieser Tabelle — sonst müsste jeder neu
     * zugeordnete Mitarbeiter zusätzlich von Hand eingetragen werden, und
     * genau das würde beim zweiten Mal vergessen.
     *
     * @return BelongsToMany<User, $this>
     */
    public function teilnehmer(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unterhaltung_teilnehmer')
            ->withPivot('gelesen_bis')
            ->withTimestamps();
    }

    public function istIntern(): bool
    {
        return $this->art === Unterhaltungsart::Intern;
    }

    /**
     * Auf das beschränken, was dieser Nutzer sehen darf.
     *
     * @param  Builder<Unterhaltung>  $query
     * @return Builder<Unterhaltung>
     */
    public function scopeSichtbarFuer(Builder $query, User $nutzer): Builder
    {
        // Ein Kundenzugang sieht genau einen Faden: den seines Kunden. Ohne
        // Kundenzuordnung gar keinen — dieser Fall ist zwar durch
        // canAccessPanel ausgeschlossen, aber die Abfrage darf sich nicht
        // darauf verlassen, sonst hinge die Trennung an einer Stelle weit weg.
        if ($nutzer->istKunde()) {
            if ($nutzer->customer_id === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query
                ->where('art', Unterhaltungsart::Kunde->value)
                ->where('customer_id', $nutzer->customer_id);
        }

        return $query->where(fn (Builder $q) => $q
            // Kundenfäden: dieselbe Zuständigkeit wie überall sonst.
            ->where(fn (Builder $k) => $k
                ->where('art', Unterhaltungsart::Kunde->value)
                ->whereHas('customer', fn (Builder $c) => $c->sichtbarFuer($nutzer)))
            // Interne Fäden: nur die eigenen, auch als Administrator.
            ->orWhere(fn (Builder $i) => $i
                ->where('art', Unterhaltungsart::Intern->value)
                ->whereHas('teilnehmer', fn (Builder $t) => $t->whereKey($nutzer->getKey()))));
    }

    /**
     * Nur die, in denen schon etwas steht.
     *
     * Eine Unterhaltung entsteht, sobald jemand den Bereich öffnet. Ohne
     * diesen Filter stünde in der Liste jeder Kunde, der einmal
     * nachgeschaut hat — und die Liste wäre nach einem Monat eine
     * Kundenliste und keine Nachrichtenübersicht mehr.
     *
     * @param  Builder<Unterhaltung>  $query
     * @return Builder<Unterhaltung>
     */
    public function scopeBegonnen(Builder $query): Builder
    {
        return $query->whereNotNull('letzte_nachricht_am');
    }

    /**
     * Wie die Unterhaltung aus Sicht dieses Nutzers heißt.
     *
     * Immer der Name des Gegenübers, nie der eigene: eine Liste, in der
     * dreimal der eigene Name steht, muss man Zeile für Zeile aufklappen.
     */
    public function titelFuer(User $nutzer): string
    {
        if (! $this->istIntern()) {
            // Der Kunde sieht auf seiner Seite nicht seinen eigenen Namen,
            // sondern uns — und zwar unter dem Namen, unter dem er uns kennt.
            // config('app.name') wäre "ND-Deck" und damit
            // der Name eines Werkzeugs, nicht der eines Ansprechpartners.
            return $nutzer->istKunde()
                ? config('kontakt.name')
                : ($this->customer?->name ?? 'Unbekannter Kunde');
        }

        $andere = $this->teilnehmer
            ->reject(fn (User $teilnehmer) => $teilnehmer->is($nutzer))
            ->map(fn (User $teilnehmer) => $teilnehmer->name);

        // Der Rückfall greift bei einer Notiz an sich selbst — möglich, wenn
        // auch selten, und allemal besser als eine leere Zeile.
        return $andere->isEmpty() ? 'Nur ich' : $andere->join(', ');
    }

    /**
     * Wie viele Nachrichten dieser Nutzer noch nicht gelesen hat.
     *
     * Eigene zählen nie mit — sonst stünde nach jedem Absenden eine Eins an
     * der eigenen Unterhaltung.
     */
    public function ungeleseneFuer(User $nutzer): int
    {
        $stand = $this->lesestandVon($nutzer);

        return $this->nachrichten()
            ->where('user_id', '!=', $nutzer->getKey())
            ->when($stand !== null, fn (Builder $q) => $q->where('created_at', '>', $stand))
            ->count();
    }

    /**
     * Alles bis jetzt als gelesen vermerken.
     *
     * Beim Öffnen aufgerufen. Legt die Teilnehmerzeile an, falls es noch
     * keine gibt — bei Kundenunterhaltungen ist das der Normalfall.
     */
    public function alsGelesenMarkieren(User $nutzer): void
    {
        $this->teilnehmer()->syncWithoutDetaching([
            $nutzer->getKey() => ['gelesen_bis' => now()],
        ]);

        // Und mit demselben Handgriff die Meldungen an der Glocke, die zu
        // diesem Verlauf gehören. Zwei Zähler für dieselbe Sache, von denen
        // man jeden einzeln wegklicken muss, sind ein Zähler zu viel: der
        // zweite steht nach einer Woche dauerhaft auf einer Zahl und wird
        // dann auch dann übersehen, wenn er einmal etwas anderes meint.
        Benachrichtigung::gesehen($nutzer, Herkunft::unterhaltung($this));

        // Die geladene Beziehung wäre sonst der Stand von vor dem Klick, und
        // die Zählung darunter zeigte die eben gelesenen Nachrichten weiter
        // als ungelesen an.
        $this->unsetRelation('teilnehmer');
    }

    /**
     * Wer diese Nachricht erfahren soll — alle Mitlesenden außer dem Absender.
     *
     * Steht am Modell und nicht in der Oberfläche: der Empfängerkreis ist die
     * Stelle, an der aus einem internen Faden ein Leck wird, und er darf
     * nicht davon abhängen, aus welchem Panel gerade geschrieben wurde.
     *
     * @return Collection<int, User>
     */
    public function empfaenger(User $absender): Collection
    {
        $empfaenger = $this->istIntern()
            ? $this->teilnehmer()->where('aktiv', true)->get()
            : $this->kreisUmDenKunden();

        return $empfaenger->reject(fn (User $nutzer) => $nutzer->is($absender))->values();
    }

    /**
     * Bei einer Kundenunterhaltung: unsere Zuständigen und die Zugänge des
     * Kunden. Beide Seiten in einer Liste, weil "wer bekommt es" nicht davon
     * abhängen soll, wer geschrieben hat — der Absender fällt in
     * empfaenger() ohnehin heraus.
     *
     * @return Collection<int, User>
     */
    private function kreisUmDenKunden(): Collection
    {
        if ($this->customer_id === null) {
            return collect();
        }

        // Beide Kreise kommen aus Benachrichtigung und werden hier nicht noch
        // einmal formuliert. Genau daran hängt, dass eine Zuordnung, die dort
        // einmal geändert wird, hier nicht anders ausfällt.
        return Benachrichtigung::zustaendige($this->customer_id)
            ->concat(Benachrichtigung::kundenzugaenge($this->customer_id));
    }

    /** Der Lesestand dieses Nutzers, oder null wenn er noch nie hier war. */
    private function lesestandVon(User $nutzer): ?Carbon
    {
        $zeile = $this->teilnehmer->firstWhere('id', $nutzer->getKey());

        $stand = $zeile?->getRelationValue('pivot')?->gelesen_bis;

        return $stand === null ? null : Carbon::parse($stand);
    }
}
