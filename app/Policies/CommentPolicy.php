<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Wer das Ticket sehen darf, sieht dessen Kommentare — mit einer
     * Ausnahme, die den ganzen Kundenbereich trägt: interne Notizen niemals.
     *
     * Die Bedingung steht hier zusätzlich zu Comment::scopeFuerKunden, das in
     * der Oberfläche filtert. Der Scope entscheidet, was in einer Liste
     * auftaucht; diese Zeile entscheidet, was ein einzelner Abruf herausgibt
     * — etwa der eines Anhangs, dessen Rechte am Kommentar hängen.
     */
    public function view(User $user, Comment $comment): bool
    {
        if ($user->istKunde() && $comment->ist_intern) {
            return false;
        }

        return $user->can('view', $comment->ticket);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Am eigenen Kommentar, sonst nur der Admin — Kunden gar nicht.
     *
     * Ein Gesprächsverlauf, in dem nachträglich etwas geändert oder gelöscht
     * werden kann, taugt als Beleg für keine der beiden Seiten. Deshalb
     * bleibt er stehen, auch der eigene Beitrag.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($comment->autor);
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($comment->autor);
    }
}
