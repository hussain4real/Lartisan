<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ReportSnapshot;
use App\Models\User;

class ReportSnapshotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canViewReports($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReportSnapshot $reportSnapshot): bool
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return true;
        }

        return $reportSnapshot->generated_by === $user->id && $this->canViewReports($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canViewReports($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReportSnapshot $reportSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReportSnapshot $reportSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReportSnapshot $reportSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReportSnapshot $reportSnapshot): bool
    {
        return false;
    }

    private function canViewReports(User $user): bool
    {
        return collect([
            PlatformPermission::ViewGlobalReports,
            PlatformPermission::ViewStateReports,
            PlatformPermission::ViewLocalGovernmentReports,
            PlatformPermission::ViewAreaReports,
        ])->contains(fn (PlatformPermission $permission): bool => $user->can($permission->value));
    }
}
