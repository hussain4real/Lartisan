<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\SupportCase;
use App\Models\User;

class SupportCasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageSupportCases->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupportCase $supportCase): bool
    {
        return $this->viewAny($user)
            && SupportCase::query()->visibleTo($user)->whereKey($supportCase->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupportCase $supportCase): bool
    {
        return $this->view($user, $supportCase);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupportCase $supportCase): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SupportCase $supportCase): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SupportCase $supportCase): bool
    {
        return false;
    }
}
