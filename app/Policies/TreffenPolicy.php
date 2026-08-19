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
            // Ein internes Treffen hat keine customer_id. Der Vergleich
            // trifft auf null nie zu, der Fall ist damit dicht — die
            // ausdrückliche Prüfung steht trotzdem da, weil "ergibt sich von
            // selbst" die Begründung ist, die eine spätere Änderung aushebelt.
            return ! $treffen->istIntern()
                && $treffen->kunden_sichtbar
                && $treffen->customer_id === $user->customer_id;
        }

        // Ein internes Treffen gehört seiner Crew — und Administratoren, die
        // ohnehin alles sehen.
        if ($treffen->istIntern()) {
            return $user->istAdmin()
                || $treffen->crew()->whereKey($user->getKey())->exists();
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
        if ($user->istKunde()) {
            return false;
        }

        return $treffen->istIntern()
            ? $this->view($user, $treffen)
            : $user->can('view', $treffen->customer);
    }

    public function delete(User $user, Treffen $treffen): bool
    {
        return $this->update($user, $treffen);
    }
}
