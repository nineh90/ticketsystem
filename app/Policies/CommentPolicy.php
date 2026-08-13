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

    public function view(User $user, Comment $comment): bool
    {
        return $user->can('view', $comment->ticket);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Am eigenen Kommentar, sonst nur der Admin. */
    public function update(User $user, Comment $comment): bool
    {
        return $user->istAdmin() || $user->is($comment->autor);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->istAdmin() || $user->is($comment->autor);
    }
}
