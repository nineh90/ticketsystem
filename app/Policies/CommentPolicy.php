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
     * Ändern darf jeder nur das, was er selbst geschrieben hat — auch der
     * Administrator.
     *
     * Vorher stand hier ein "istAdmin() ||", und damit ließ sich der
     * Kommentar eines Kunden nachträglich umschreiben. Das ist die eine
     * Sorte Rechte, die man nicht haben will: was der Kunde geschrieben hat,
     * ist seine Aussage. Wer sie ändern kann, macht den ganzen Verlauf als
     * Beleg wertlos — für beide Seiten, und im Streitfall gegen uns.
     *
     * Kunden ändern gar nichts nachträglich, auch nicht den eigenen Beitrag:
     * für sie ist der Verlauf das, worauf sie sich berufen, und dieselbe
     * Überlegung gilt in beide Richtungen.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->is($comment->autor);
    }

    /**
     * Löschen darf man den eigenen Beitrag — und der Administrator jeden.
     *
     * Das ist die Ausnahme mit Ansage: ein unangemessener oder versehentlich
     * öffentlich geschriebener Kommentar muss weg können. Löschen ist dabei
     * ehrlicher als Ändern — danach steht dort nichts, und niemand liest
     * einen Satz, den der Urheber so nie geschrieben hat.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($comment->autor);
    }
}
