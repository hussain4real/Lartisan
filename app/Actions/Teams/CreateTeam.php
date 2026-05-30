<?php

namespace App\Actions\Teams;

use App\Enums\TeamKind;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'kind' => $isPersonal ? TeamKind::Personal : TeamKind::Workspace,
                'is_personal' => $isPersonal,
            ]);

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
