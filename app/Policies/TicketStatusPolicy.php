<?php

namespace App\Policies;

use App\Models\TicketStatus;
use App\Models\User;

/**
 * Die Stadien sind der Arbeitsablauf des ganzen Hauses — daran schraubt nur
 * der Admin. Ein gelöschtes Stadium würde außerdem Tickets ohne Status
 * hinterlassen; die Fremdschlüssel-Beschränkung (restrictOnDelete) fängt das
 * ab, aber die Policy sorgt dafür, dass es gar nicht erst versucht wird.
 */
class TicketStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->istAdmin();
    }

    public function view(User $user, TicketStatus $status): bool
    {
        return $user->istAdmin();
    }

    public function create(User $user): bool
    {
        return $user->istAdmin();
    }

    public function update(User $user, TicketStatus $status): bool
    {
        return $user->istAdmin();
    }

    public function delete(User $user, TicketStatus $status): bool
    {
        return $user->istAdmin() && $status->tickets()->doesntExist();
    }
}
