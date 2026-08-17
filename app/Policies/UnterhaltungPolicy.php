<?php

namespace App\Policies;

use App\Models\Unterhaltung;
use App\Models\User;

/**
 * Wer welchen Faden lesen und beschreiben darf.
 *
 * Die Regel steht bewusst als Policy und nicht nur als Abfrage-Scope: der
 * Scope beantwortet "was steht in meiner Liste", die Policy beantwortet "darf
 * ich diese eine Unterhaltung öffnen". Die zweite Frage stellt sich, sobald
 * jemand eine Nummer in der Adresszeile ändert — und genau dann hilft ein
 * Scope nicht mehr.
 *
 * Der Unterschied zu allem anderen in diesem System: bei einem internen Faden
 * hat auch ein Administrator nichts zu suchen. Sonst ist "Administrator sieht
 * alles" die Regel; hier wäre sie der Grund, den internen Draht nach dem
 * ersten Mal nicht mehr zu benutzen.
 */
class UnterhaltungPolicy
{
    public function viewAny(User $user): bool
    {
        // Kunden dürfen ausdrücklich — ihr eigener Faden ist der halbe Zweck
        // der Sache. Was sie sehen, beschränkt der Scope auf genau einen.
        return $user->aktiv;
    }

    public function view(User $user, Unterhaltung $unterhaltung): bool
    {
        if (! $user->aktiv) {
            return false;
        }

        if ($unterhaltung->istIntern()) {
            // Kein Sonderweg für Administratoren, siehe oben.
            return $user->istKunde() === false
                && $unterhaltung->teilnehmer->contains(fn (User $teilnehmer) => $teilnehmer->is($user));
        }

        if ($user->istKunde()) {
            return $user->customer_id !== null
                && $user->customer_id === $unterhaltung->customer_id;
        }

        // Dieselbe Zuständigkeit wie bei Tickets, Zeiten und Projekten. Die
        // Abfrage geht über den bestehenden Scope, damit hier keine zweite
        // Fassung derselben Regel entsteht.
        return $unterhaltung->customer_id !== null
            && $user->istBerechtigtFuerKunde($unterhaltung->customer_id);
    }

    /** Antworten darf, wer lesen darf — eine Unterhaltung ohne Rückweg wäre keine. */
    public function schreiben(User $user, Unterhaltung $unterhaltung): bool
    {
        return $this->view($user, $unterhaltung);
    }

    /**
     * Einen neuen Faden beginnen dürfen nur wir.
     *
     * Für den Kunden gibt es nichts zu beginnen: sein Faden ist immer
     * derselbe und entsteht, sobald er den Bereich öffnet.
     */
    public function create(User $user): bool
    {
        return $user->aktiv && ! $user->istKunde();
    }
}
