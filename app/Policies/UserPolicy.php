<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $profile): bool
    {
        return $user->canAccessWorkspace() && $user->is($profile);
    }

    public function changePassword(User $user, User $profile): bool
    {
        return $this->view($user, $profile);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }
}
