<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NumberingConfigPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can manage numbering configurations.
     */
    public function manageNumberingConfig(User $user)
    {
        return $user->hasRole(['super-admin', 'admin', 'finance-manager']);
    }
}
