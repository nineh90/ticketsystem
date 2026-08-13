<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

/**
 * Anhänge erben die Rechte ihres Tickets.
 *
 * Wer das Ticket sehen darf, sieht auch dessen Anhänge — und wer es nicht
 * darf, kommt auch über die Ausliefer-Route nicht an die Datei. Das ist der
 * ganze Grund, warum die Dateien nicht im öffentlichen Ordner liegen.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $anhang): bool
    {
        return $user->can('view', $anhang->ticket);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Eigene Uploads, sonst nur der Admin. */
    public function delete(User $user, Attachment $anhang): bool
    {
        return $user->istAdmin() || $user->is($anhang->hochgeladenVon);
    }
}
