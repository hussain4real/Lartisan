<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\SubscriptionPlan;
use App\Models\User;

class SubscriptionPlanPolicy
{
    /**
     * Determine whether the user can view any subscription plans.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can view a subscription plan.
     */
    public function view(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can create subscription plans.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can update a subscription plan.
     */
    public function update(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can delete an unused subscription plan.
     */
    public function delete(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value)
            && ! $subscriptionPlan->subscriptions()->exists()
            && ! $subscriptionPlan->payments()->exists();
    }

    /**
     * Determine whether the user can restore a deleted subscription plan.
     */
    public function restore(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete a subscription plan.
     */
    public function forceDelete(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return false;
    }
}
