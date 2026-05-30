<?php

namespace App\Http\Controllers\Artisan;

use App\Models\ArtisanProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesCurrentArtisanProfile
{
    protected function userFrom(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpException(403);
        }

        return $user;
    }

    protected function artisanProfileFrom(Request $request): ArtisanProfile
    {
        $team = $this->userFrom($request)->currentTeam()->first();

        if (! $team instanceof Team) {
            throw new NotFoundHttpException('No current team is available.');
        }

        $profile = $team->artisanProfile()->first();

        if (! $profile instanceof ArtisanProfile) {
            throw new NotFoundHttpException('No artisan profile is attached to the current team.');
        }

        return $profile;
    }
}
