<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Kunden verwaltet nur der Admin.
 *
 * Mitarbeiter dürfen sie sehen, soweit sie ein Projekt dieses Kunden haben —
 * sonst stünden in Auswahllisten und Ticketkennungen Namen von Kunden, mit
 * denen sie nichts zu tun haben.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->istAdmin()) {
            return true;
        }

        return $customer->projects()
            ->whereHas('mitarbeiter', fn ($q) => $q->whereKey($user->getKey()))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->istAdmin();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->istAdmin();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->istAdmin();
    }
}
