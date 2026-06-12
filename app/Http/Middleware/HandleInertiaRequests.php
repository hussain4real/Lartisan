<?php

namespace App\Http\Middleware;

use App\Enums\PlatformPermission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'operationPanel' => $this->operationPanelFor($user),
                'teamManagement' => [
                    'canView' => $this->canViewTeamManagement($user),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }

    /**
     * @return array{id: 'admin'|'state'|'lga'|'agent', title: string}|null
     */
    private function operationPanelFor(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return ['id' => 'admin', 'title' => 'Admin panel'];
        }

        if ($user->can(PlatformPermission::ViewStateReports->value)) {
            return ['id' => 'state', 'title' => 'State panel'];
        }

        if ($user->can(PlatformPermission::ViewLocalGovernmentReports->value)) {
            return ['id' => 'lga', 'title' => 'LGA panel'];
        }

        if ($user->can(PlatformPermission::ViewAreaReports->value)) {
            return ['id' => 'agent', 'title' => 'Agent panel'];
        }

        return null;
    }

    private function canViewTeamManagement(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return Gate::forUser($user)->allows('viewAny', Team::class);
    }
}
