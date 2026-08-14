<?php

namespace App\Observers;

use App\Models\Comment;
use App\Support\Benachrichtigung;
use Filament\Notifications\Notification;

/**
 * Antworten in beide Richtungen melden.
 *
 * Ohne das wäre der Kundenbereich eine Einbahnstraße mit Wartesaal: der Kunde
 * schreibt etwas und schaut danach täglich nach, ob jemand geantwortet hat —
 * oder er ruft an, und dann hätten wir das Ticketsystem auch weglassen können.
 */
class CommentObserver
{
    public function created(Comment $comment): void
    {
        // Interne Notizen bleiben intern. Das ist die eine Bedingung, an der
        // hier alles hängt: ein Kommentar mit ist_intern = true darf einen
        // Kundenzugang nicht einmal in Form einer Benachrichtigung erreichen,
        // denn deren Text ist ein Auszug daraus.
        if ($comment->ist_intern) {
            return;
        }

        $comment->loadMissing(['ticket.project', 'ticket.customer', 'autor']);

        $ticket = $comment->ticket;

        if ($ticket === null) {
            return;
        }

        $auszug = str($comment->body)->stripTags()->squish()->limit(120)->toString();

        if ($comment->autor?->istKunde()) {
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
        );
    }
}
