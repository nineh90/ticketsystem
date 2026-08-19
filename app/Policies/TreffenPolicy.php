<?php

namespace App\Policies;

use App\Models\Treffen;
use App\Models\User;

/**
 * Wer welches Treffen sieht und wer es ändern darf.
 *
 * Dieselbe Doppelung wie bei den Dokumenten, und aus demselben Grund: der
 * Scope am Modell füllt Listen, diese Klasse bewacht den Direktaufruf. Der
 * einzige Direktaufruf ist heute der Kalendereintrag — eine Adresse mit einer
 * laufenden Nummer darin, also genau die Sorte, die man durchprobieren kann.
 */
class TreffenPolicy
{
    public function view(User $user, Treffen $treffen): bool
    {
        if ($user->istKunde()) {
            return $treffen->kunden_sichtbar
                && $treffen->customer_id === $user->customer_id;
        }

        return $user->can('view', $treffen->customer);
    }

    /**
     * Termine macht die Crew. Ein Kunde, der sich selbst einen Termin in
     * unseren Kalender legt, wäre etwas anderes als das hier — und ein
     * Wunsch, den er heute über eine Nachricht äußert.
     */
    public function create(User $user): bool
    {
        return ! $user->istKunde();
    }

    public function update(User $user, Treffen $treffen): bool
    {
        return ! $user->istKunde() && $user->can('view', $treffen->customer);
    }

    public function delete(User $user, Treffen $treffen): bool
    {
        return $this->update($user, $treffen);
    }
}
