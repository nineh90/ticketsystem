<?php

namespace App\Livewire;

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Die Kommentare an einem Ticket als Gespräch (NID-36).
 *
 * Vorher standen sie als Tabelle in einem Reiter neben Anhängen, Zeiten und
 * Aktivität — das Neueste oben, Spalten "Von / Kommentar / Intern / Wann".
 * Das liest sich wie eine Datenliste, nicht wie ein Gespräch, und eine Frage
 * sah genauso aus wie eine Notiz. Jetzt steht der Verlauf direkt unter der
 * Beschreibung, das Älteste oben, und man sieht jedem Beitrag an, für wen er
 * ist: intern, für den Kunden sichtbar oder vom Kunden.
 *
 * Eine eigene Komponente statt Teil der Ticketseite: der Verlauf lädt sich
 * im Takt neu, und das soll nicht jedes Mal die ganze Seite tun.
 *
 * Jede Methode hier ist von außen aufrufbar, auch mit fremden Nummern. Die
 * Rechte werden deshalb in jeder einzeln geprüft (CommentPolicy), nicht nur
 * an den Knöpfen.
 */
class Ticketverlauf extends Component
{
    #[Locked]
    public int $ticketId;

    public string $entwurf = '';

    /** Voreingestellt: ein vergessener Haken darf nichts an den Kunden geben. */
    public bool $intern = true;

    public ?int $frageAn = null;

    public ?int $bearbeitet = null;

    public string $bearbeitetText = '';

    public function mount(Ticket $ticket): void
    {
        $this->ticketId = $ticket->getKey();
        $this->ticket();
    }

    /** Das Ticket — jedes Mal mit der Frage, ob man es sehen darf. */
    private function ticket(): Ticket
    {
        $nutzer = auth()->user();

        // Zuerst: der Kundenbereich hat seinen eigenen Verlauf ohne interne
        // Notizen (AntwortenRelationManager). Dieser hier zeigt alles.
        abort_if($nutzer === null || $nutzer->istKunde(), 403);

        return Ticket::query()->sichtbarFuer($nutzer)->findOrFail($this->ticketId);
    }

    /** @return Collection<int, Comment> */
    public function kommentare(): Collection
    {
        return $this->ticket()->comments()
            ->with(['autor', 'gefragt'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Wen man fragen kann: wer aus dem Team das Ticket sieht, außer mir.
     *
     * @return Collection<int, User>
     */
    public function fragbare(): Collection
    {
        $ticket = $this->ticket();

        return User::query()->intern()->whereKeyNot(auth()->id())->orderBy('name')->get()
            ->filter(fn (User $nutzer) => $nutzer->can('view', $ticket))
            ->values();
    }

    public function senden(): void
    {
        $ticket = $this->ticket();
        $text = trim($this->entwurf);

        if ($text === '') {
            return;
        }

        if (auth()->user()->cannot('create', Comment::class)) {
            throw ValidationException::withMessages(['entwurf' => 'Hier darfst du nicht schreiben.']);
        }

        // Eine Frage gibt es nur intern: sie richtet sich an jemanden aus dem
        // Team, und der Kunde soll sie nicht als Antwort an sich lesen.
        $frageAn = $this->intern && $this->frageAn !== null
            ? $this->fragbare()->firstWhere('id', $this->frageAn)?->getKey()
            : null;

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => auth()->id(),
            'body' => $text,
            'ist_intern' => $this->intern,
            'frage_an' => $frageAn,
        ]);

        $this->reset('entwurf', 'frageAn');
        $this->intern = true;
    }

    public function bearbeiten(int $id): void
    {
        $kommentar = $this->kommentar($id);
        $this->authorize('update', $kommentar);

        $this->bearbeitet = $kommentar->getKey();
        $this->bearbeitetText = $kommentar->body;
    }

    public function speichern(): void
    {
        if ($this->bearbeitet === null) {
            return;
        }

        $kommentar = $this->kommentar($this->bearbeitet);
        $this->authorize('update', $kommentar);

        if (trim($this->bearbeitetText) !== '') {
            $kommentar->update(['body' => trim($this->bearbeitetText)]);
        }

        $this->abbrechen();
    }

    public function abbrechen(): void
    {
        $this->reset('bearbeitet', 'bearbeitetText');
    }

    public function loeschen(int $id): void
    {
        $kommentar = $this->kommentar($id);
        $this->authorize('delete', $kommentar);

        $kommentar->delete();
    }

    /**
     * Von Hand abhaken — für die Antwort, die nicht hier stand (am Telefon,
     * in einer Nachricht). Darf der Gefragte, der Fragende und ein Admin.
     */
    public function beantwortet(int $id): void
    {
        $kommentar = $this->kommentar($id);
        $nutzer = auth()->user();

        abort_unless(
            $nutzer->istAdmin() || $kommentar->frage_an === $nutzer->getKey() || $kommentar->user_id === $nutzer->getKey(),
            403,
        );

        $kommentar->update(['beantwortet_at' => now()]);
    }

    private function kommentar(int $id): Comment
    {
        return $this->ticket()->comments()->findOrFail($id);
    }

    public function render(): View
    {
        return view('filament.ticketverlauf', [
            'kommentare' => $this->kommentare(),
            'fragbare' => $this->fragbare(),
            'ich' => auth()->user(),
        ]);
    }
}
