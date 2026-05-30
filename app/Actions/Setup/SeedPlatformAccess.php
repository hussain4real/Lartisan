<?php

namespace App\Actions\Setup;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SeedPlatformAccess
{
    /**
     * Seed Spatie roles and permissions for the Lartisan operating model.
     */
    public function handle(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PlatformPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PlatformRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions($this->permissionsFor($role));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    private function permissionsFor(PlatformRole $role): array
    {
        return array_map(
            fn (PlatformPermission $permission): string => $permission->value,
            match ($role) {
                PlatformRole::SuperAdmin => PlatformPermission::cases(),
                PlatformRole::StateCoordinator => [
                    PlatformPermission::ManageLocalGovernmentAdmins,
                    PlatformPermission::ManageAreaAgents,
                    PlatformPermission::ManageTerritories,
                    PlatformPermission::AssignTerritories,
                    PlatformPermission::ReviewEscalatedKyc,
                    PlatformPermission::ModerateArtisanProfiles,
                    PlatformPermission::ViewScopedBookings,
                    PlatformPermission::ManageBookingExceptions,
                    PlatformPermission::ViewPayments,
                    PlatformPermission::ManageSupportCases,
                    PlatformPermission::ViewStateReports,
                    PlatformPermission::ViewLocalGovernmentReports,
                    PlatformPermission::ViewAreaReports,
                ],
                PlatformRole::LocalGovernmentAdmin => [
                    PlatformPermission::ManageAreaAgents,
                    PlatformPermission::ManageTerritories,
                    PlatformPermission::AssignTerritories,
                    PlatformPermission::ReviewStandardKyc,
                    PlatformPermission::ModerateArtisanProfiles,
                    PlatformPermission::ViewScopedBookings,
                    PlatformPermission::ManageBookingExceptions,
                    PlatformPermission::ManageSupportCases,
                    PlatformPermission::ViewLocalGovernmentReports,
                    PlatformPermission::ViewAreaReports,
                ],
                PlatformRole::AreaAgent => [
                    PlatformPermission::SubmitFieldKyc,
                    PlatformPermission::ViewScopedBookings,
                    PlatformPermission::ManageSupportCases,
                    PlatformPermission::ViewAreaReports,
                ],
                PlatformRole::Artisan => [
                    PlatformPermission::ManageOwnArtisanProfile,
                    PlatformPermission::ManageOwnServices,
                    PlatformPermission::ManageOwnSubscription,
                    PlatformPermission::ViewOwnWallet,
                ],
                PlatformRole::Customer => [
                    PlatformPermission::ManageOwnBookings,
                    PlatformPermission::CreateReviews,
                ],
                PlatformRole::GuestCustomer => [
                    PlatformPermission::CreateGuestBookings,
                ],
            },
        );
    }
}
