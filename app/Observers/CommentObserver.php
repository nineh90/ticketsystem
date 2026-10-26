<?php

namespace App\Observers;

use App\Enums\MailEreignis;
use App\Models\Comment;
use App\Models\User;
use App\Support\Automatik;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Notifications\Notification;

/**
 * Antworten in beide Richtungen melden.
 *
 * Ohne das wäre der Kundenbereich eine Einbahnstraße mit Wartesaal: der Kunde
 * schreibt etwas und schaut danach täglich nach, ob jemand geantwortet hat —
 * oder er ruft an, und dann hätten wir das Ticketsystem auch weglassen können.
 *
 * Antwortet der Kunde, endet außerdem die Wartestellung: das Ticket wandert
 * zurück zu uns (Support\Automatik::ausDerWartestellung). "Warten auf Kunde"
 * ist das einzige Stadium, dessen Bedingung von außen endet — und es blieb
 * bis dahin trotzdem stehen, bis jemand von uns die Spalte durchging.
 */
class CommentObserver
{
    /**
     * Wie viel vom Kommentar in Glocke und Mail steht.
     *
     * Vorher 120 Zeichen — damit war eine Frage meist abgeschnitten, bevor
     * das Fragezeichen kam, und man musste das Ticket öffnen, um zu wissen,
     * ob man jetzt gleich etwas tun muss (NID-36).
     */
    public const AUSZUG_ZEICHEN = 500;

    public function created(Comment $comment): void
    {
        $comment->loadMissing(['ticket.project', 'ticket.customer', 'autor', 'gefragt']);

        $ticket = $comment->ticket;

        if ($ticket === null) {
            return;
        }

        $this->fragenBeantworten($comment);

        $auszug = str($comment->body)->stripTags()->squish()->limit(self::AUSZUG_ZEICHEN)->toString();

        // Interne Notizen bleiben intern. Das ist die eine Bedingung, an der
        // hier alles hängt: ein Kommentar mit ist_intern = true darf einen
        // Kundenzugang nicht einmal in Form einer Benachrichtigung erreichen,
        // denn deren Text ist ein Auszug daraus. Gemeldet wird er trotzdem —
        // nur eben nach innen.
        if ($comment->ist_intern) {
            $this->imTeamMelden($comment, $auszug);

            return;
        }

        if ($comment->autor?->istKunde()) {
            // Er hat geantwortet, also warten wir nicht mehr auf ihn. Das
            // Ticket kommt von selbst aus der Wartestellung zurück — siehe
            // Support\Automatik.
            Automatik::ausDerWartestellung($ticket);

            Benachrichtigung::nachInnen(
                $ticket,
                Notification::make()
                    ->title('Antwort von '.$ticket->customer->name)
                    ->body($ticket->kennung().' — '.$auszug)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->actions([
                        Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlIntern($ticket)),
                    ]),
                MailEreignis::Antwort,
            );

            return;
        }

        Benachrichtigung::nachAussen(
            $ticket,
            Notification::make()
                ->title('Neue Antwort zu '.$ticket->kennung())
                ->body($auszug)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->actions([
                    Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlKunde($ticket)),
                ]),
            MailEreignis::AntwortAnKunde,
        );
    }

    /**
     * Wer gefragt war und jetzt am selben Ticket schreibt, hat geantwortet.
     *
     * Von selbst, weil "als beantwortet markieren" der Handgriff ist, den
     * man nach dem Antworten vergisst — und eine Frage, die nach der Antwort
     * offen stehen bleibt, macht die Liste der offenen Fragen wertlos.
     */
    private function fragenBeantworten(Comment $comment): void
    {
        if ($comment->user_id === null) {
            return;
        }

        Comment::query()
            ->where('ticket_id', $comment->ticket_id)
            ->whereKeyNot($comment->getKey())
            ->where('frage_an', $comment->user_id)
            ->offeneFragen()
            ->update(['beantwortet_at' => now()]);
    }

    /**
     * Eine interne Notiz an die, die sie angeht — oder eine Frage an den,
     * der gefragt ist.
     *
     * Vorher ging eine interne Notiz an niemanden. Kevins Fragen an Nils
     * standen an Tickets, die Nils nicht öffnete, und gingen genau so unter,
     * wie man es erwartet (NID-36).
     *
     * Die Frage geht sofort und als Frage: der, der fragt, wartet mit seiner
     * Arbeit. Die Notiz geht an den Innenkreis, ohne den Schreiber und —
     * wie in der Sammelmail — ohne Mitarbeiter, deren Ticket es nicht ist:
     * Kevin steht in Projekten, deren Tickets bei Nils liegen, und Nils'
     * Notizen dort sind nicht für ihn.
     */
    private function imTeamMelden(Comment $comment, string $auszug): void
    {
        $ticket = $comment->ticket;
        $von = $comment->autor?->name ?? 'Jemand';
        $knopf = Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlIntern($ticket));

        $gefragt = $comment->gefragt;

        if ($gefragt !== null && $gefragt->aktiv && ! $gefragt->is($comment->autor)) {
            Benachrichtigung::an(
                collect([$gefragt]),
                Notification::make()
                    ->title('Frage von '.$von.' zu '.$ticket->kennung())
                    ->body($auszug)
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('warning')
                    ->actions([Benachrichtigung::knopf('Antworten', Benachrichtigung::urlIntern($ticket))]),
                Herkunft::ticket($ticket),
                $ticket->customer,
                MailEreignis::Frage,
            );
        }

        $kreis = Benachrichtigung::innenkreis($ticket)
            ->reject(fn (User $nutzer) => $nutzer->is($comment->autor) || $nutzer->is($gefragt))
            ->filter(fn (User $nutzer) => $nutzer->istAdmin()
                || $ticket->assigned_to === null
                || $ticket->assigned_to === $nutzer->getKey())
            ->values();

        if ($kreis->isEmpty()) {
            return;
        }

        Benachrichtigung::an(
            $kreis,
            Notification::make()
                ->title(($gefragt !== null ? 'Frage' : 'Notiz').' von '.$von.' zu '.$ticket->kennung())
                ->body($auszug)
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->actions([$knopf]),
            Herkunft::ticket($ticket),
            $ticket->customer,
            MailEreignis::Kommentar,
        );
    }
}
