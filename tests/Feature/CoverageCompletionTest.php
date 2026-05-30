<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Http\Requests\Teams\AcceptTeamInvitationRequest;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use App\Http\Responses\LoginResponse;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Policies\TeamPolicy;
use App\Providers\AppServiceProvider;
use App\Rules\TeamName;
use App\Rules\UniqueTeamInvitation;
use App\Rules\ValidTeamInvitation;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function jsonFortifyRequest(): Request
{
    $request = Request::create('/fortify-response', 'POST', server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $request->setLaravelSession(app('session.store'));

    return $request;
}

function validationFailuresFor(object $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('value', $value, function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

/**
 * @template TFormRequest of FormRequest
 *
 * @param  class-string<TFormRequest>  $formRequest
 * @param  array<string, mixed>  $parameters
 * @return TFormRequest
 */
function coverageFormRequest(string $formRequest, string $uri = '/', string $method = 'GET', array $parameters = []): FormRequest
{
    /** @var TFormRequest $request */
    $request = $formRequest::create($uri, $method, $parameters);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session.store'));

    return $request;
}

test('team slugs ignore non numeric suffixes when finding the next suffix', function () {
    Team::factory()->create(['name' => 'Acme Alpha', 'slug' => 'acme-alpha']);

    $team = Team::factory()->create(['name' => 'Acme', 'slug' => null]);

    expect($team->slug)->toBe('acme-1');
});

test('team ownership helpers expose owned teams and permission checks', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();
    $ownedTeam = Team::factory()->create();
    $memberTeam = Team::factory()->create();

    $ownedTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $memberTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->ownedTeams()->pluck('teams.id')->all())
        ->toContain($personalTeam->id, $ownedTeam->id)
        ->not->toContain($memberTeam->id);
    expect($user->ownsTeam($ownedTeam))->toBeTrue();
    expect($user->ownsTeam($memberTeam))->toBeFalse();
    expect($user->hasTeamPermission($ownedTeam, TeamPermission::DeleteTeam))->toBeTrue();
    expect($user->hasTeamPermission($memberTeam, TeamPermission::DeleteTeam))->toBeFalse();
});

test('switching teams fails without membership and leaves the current team unchanged', function () {
    $user = User::factory()->create();
    $currentTeamId = $user->current_team_id;
    $otherTeam = Team::factory()->create();

    expect($user->switchTeam($otherTeam))->toBeFalse();
    expect($user->fresh()->current_team_id)->toBe($currentTeamId);
});

test('team roles expose hierarchy and assignable role metadata', function () {
    expect(TeamRole::Owner->level())->toBe(3);
    expect(TeamRole::Admin->level())->toBe(2);
    expect(TeamRole::Member->level())->toBe(1);
    expect(TeamRole::Owner->isAtLeast(TeamRole::Admin))->toBeTrue();
    expect(TeamRole::Member->isAtLeast(TeamRole::Admin))->toBeFalse();
    expect(TeamRole::assignable())->toBe([
        ['value' => 'admin', 'label' => 'Admin'],
        ['value' => 'member', 'label' => 'Member'],
    ]);
});

test('membership model exposes related team user and role cast', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

    $membership = Membership::query()
        ->where('team_id', $team->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($membership->role)->toBe(TeamRole::Admin);
    expect($membership->team->is($team))->toBeTrue();
    expect($membership->user->is($user))->toBeTrue();
});

test('team invitation model reports generated codes inviter and pending state', function () {
    $inviter = User::factory()->create();
    $team = Team::factory()->create();
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $inviter->id,
        'code' => null,
        'accepted_at' => null,
        'expires_at' => null,
    ]);

    expect($invitation->code)->toBeString()->toHaveLength(64);
    expect($invitation->inviter->is($inviter))->toBeTrue();
    expect($invitation->isAccepted())->toBeFalse();
    expect($invitation->isPending())->toBeTrue();
    expect($invitation->isExpired())->toBeFalse();
});

test('team invitation notification renders mail and array payloads', function () {
    $inviter = User::factory()->create(['name' => 'Ada Manager']);
    $team = Team::factory()->create(['name' => 'Growth Guild']);
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $inviter->id,
        'role' => TeamRole::Admin,
    ]);
    $notification = new TeamInvitationNotification($invitation);

    $mail = $notification->toMail((object) ['email' => $invitation->email]);

    expect($notification->via((object) []))->toBe(['mail']);
    expect($mail->subject)->toBe("You've been invited to join Growth Guild");
    expect($mail->introLines[0])->toBe('Ada Manager has invited you to join the Growth Guild team.');
    expect($mail->actionText)->toBe('Accept invitation');
    expect($mail->actionUrl)->toBe(url("/invitations/{$invitation->code}/accept"));
    expect($notification->toArray((object) []))->toBe([
        'invitation_id' => $invitation->id,
        'team_id' => $team->id,
        'team_name' => 'Growth Guild',
        'role' => TeamRole::Admin->value,
    ]);
});

test('team policy exposes base permissions and delegated team abilities', function () {
    $policy = new TeamPolicy;
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $team = Team::factory()->create(['is_personal' => false]);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($policy->viewAny($owner))->toBeTrue();
    expect($policy->create($owner))->toBeTrue();
    expect($policy->view($owner, $team))->toBeTrue();
    expect($policy->view($outsider, $team))->toBeFalse();
    expect($policy->addMember($owner, $team))->toBeTrue();
    expect($policy->addMember($admin, $team))->toBeFalse();
    expect($policy->updateMember($owner, $team))->toBeTrue();
    expect($policy->removeMember($owner, $team))->toBeTrue();
    expect($policy->inviteMember($admin, $team))->toBeTrue();
    expect($policy->cancelInvitation($admin, $team))->toBeTrue();
    expect($policy->delete($owner, $team))->toBeTrue();
});

test('team membership middleware enforces minimum role and switches current team from slug routes', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->update(['current_team_id' => $user->personalTeam()->id]);

    $path = '/coverage-membership-'.uniqid().'/{current_team}';
    Route::get($path, fn () => 'ok')
        ->middleware(['auth', EnsureTeamMembership::class.':admin']);

    $this
        ->actingAs($user)
        ->get(str_replace('{current_team}', $team->slug, $path))
        ->assertOk();

    expect($user->fresh()->current_team_id)->toBe($team->id);
});

test('team membership middleware rejects invalid minimum roles', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $path = '/coverage-invalid-membership-'.uniqid().'/{team}';
    Route::get($path, fn () => 'ok')
        ->middleware(['auth', EnsureTeamMembership::class.':super-owner']);

    $this
        ->actingAs($user)
        ->get(str_replace('{team}', $team->slug, $path))
        ->assertForbidden();
});

test('fortify response contracts return json payloads', function () {
    expect(app(LoginResponseContract::class)->toResponse(jsonFortifyRequest())->getData(true))
        ->toBe(['two_factor' => false]);
    expect(app(RegisterResponseContract::class)->toResponse(jsonFortifyRequest())->getStatusCode())
        ->toBe(201);
    expect(app(TwoFactorLoginResponseContract::class)->toResponse(jsonFortifyRequest())->getData(true))
        ->toBe(['two_factor' => false]);
    expect(app(VerifyEmailResponseContract::class)->toResponse(jsonFortifyRequest())->getStatusCode())
        ->toBe(204);
});

test('current team redirects fail when a user has no available team', function () {
    $user = User::query()->create([
        'name' => 'No Team User',
        'email' => 'no-team@example.com',
        'password' => Hash::make('password'),
    ]);
    $request = Request::create('/login', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    app(LoginResponse::class)->toResponse($request);
})->throws(HttpException::class);

test('fortify rate limiters use session credential and fallback keys', function () {
    $twoFactorRequest = Request::create('/two-factor-challenge', 'POST');
    $twoFactorRequest->setLaravelSession(app('session.store'));
    $twoFactorRequest->session()->put('login.id', 123);

    $passkeyRequest = Request::create('/passkey', 'POST', [
        'credential' => ['id' => 'credential-123'],
    ]);
    $passkeyRequest->setLaravelSession(app('session.store'));

    $fallbackPasskeyRequest = Request::create('/passkey', 'POST');
    $fallbackPasskeyRequest->setLaravelSession(app('session.store'));

    expect(RateLimiter::limiter('two-factor')($twoFactorRequest)->key)->toBe(123);
    expect(RateLimiter::limiter('passkeys')($passkeyRequest)->key)->toContain('credential-123|');
    expect(RateLimiter::limiter('passkeys')($fallbackPasskeyRequest)->key)
        ->toContain($fallbackPasskeyRequest->session()->getId().'|');
});

test('defensive controller branches reject requests without resolved users', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($owner);

    $callbacks = [
        fn () => app(ProfileController::class)->update(coverageFormRequest(ProfileUpdateRequest::class)),
        fn () => app(ProfileController::class)->destroy(coverageFormRequest(ProfileDeleteRequest::class)),
        fn () => app(SecurityController::class)->edit(coverageFormRequest(TwoFactorAuthenticationRequest::class)),
        fn () => app(SecurityController::class)->update(coverageFormRequest(PasswordUpdateRequest::class)),
        fn () => app(TeamController::class)->index(Request::create('/teams', 'GET')),
        fn () => app(TeamController::class)->store(coverageFormRequest(SaveTeamRequest::class), app(CreateTeam::class)),
        fn () => app(TeamController::class)->edit(Request::create("/{$team->slug}/teams/{$team->slug}", 'GET'), $team),
        fn () => app(TeamController::class)->switch(Request::create("/teams/{$team->slug}/switch", 'POST'), $team),
        fn () => app(TeamController::class)->destroy(coverageFormRequest(DeleteTeamRequest::class), $team),
        fn () => app(TeamInvitationController::class)->store(coverageFormRequest(CreateTeamInvitationRequest::class), $team),
        fn () => app(TeamInvitationController::class)->accept(coverageFormRequest(AcceptTeamInvitationRequest::class), $invitation),
    ];

    foreach ($callbacks as $callback) {
        expect($callback)->toThrow(HttpException::class);
    }
});

test('team member payload ignores unexpected pivot instances', function () {
    $controller = new class extends TeamController
    {
        /**
         * @return array{id: int, name: string, email: string, avatar: string|null, role: string, role_label: string}|null
         */
        public function payloadFor(User $member): ?array
        {
            return $this->memberPayload($member);
        }
    };

    $member = User::factory()->make();
    $member->setRelation('pivot', new Pivot);

    expect($controller->payloadFor($member))->toBeNull();
});

test('team invitation request guards invalid route bindings and keeps parent data', function () {
    $acceptRequest = coverageFormRequest(AcceptTeamInvitationRequest::class, parameters: [
        'source' => 'email',
    ]);

    expect($acceptRequest->validationData())->toMatchArray([
        'source' => 'email',
        'invitation' => null,
    ]);

    $createRequest = coverageFormRequest(CreateTeamInvitationRequest::class);
    $route = new Illuminate\Routing\Route(['POST'], '/teams/{team}/invitations', []);
    $route->bind($createRequest);
    $route->setParameter('team', 'missing-team');
    $createRequest->setRouteResolver(fn () => $route);

    expect(fn () => $createRequest->rules())->toThrow(NotFoundHttpException::class);
});

test('current team redirect rejects requests without a resolved user', function () {
    $redirector = new class
    {
        use RedirectsToCurrentTeam;

        public function pathFor(Request $request): string
        {
            return $this->redirectPathForCurrentTeam($request, '/dashboard');
        }
    };

    expect(fn () => $redirector->pathFor(Request::create('/login', 'POST')))
        ->toThrow(HttpException::class);
});

test('team validation rules reject non string values', function () {
    $team = Team::factory()->create();

    expect(validationFailuresFor(new TeamName, ['settings']))->toHaveCount(1);
    expect(validationFailuresFor(new UniqueTeamInvitation($team), ['owner@example.com']))->toHaveCount(1);
});

test('app service provider configures production password defaults', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $provider = new class($this->app) extends AppServiceProvider
    {
        public function configureForCoverage(): void
        {
            $this->configureDefaults();
        }
    };

    $provider->configureForCoverage();

    expect(Password::default())->toBeInstanceOf(Password::class);

    Password::defaults(fn (): ?Password => null);
    $this->app->detectEnvironment(fn () => 'testing');
});

test('team name rule rejects reserved names from configured routes', function () {
    expect(validationFailuresFor(new TeamName, 'settings'))->toHaveCount(1);
    expect(validationFailuresFor(new TeamName, 'a local guild'))->toBe([]);
});

test('valid team invitation rule rejects invalid and accepted invitations', function () {
    $user = User::factory()->create(['email' => 'invited@example.com']);
    $acceptedInvitation = TeamInvitation::factory()->accepted()->create([
        'email' => 'invited@example.com',
    ]);

    expect(validationFailuresFor(new ValidTeamInvitation($user), 'not-an-invitation'))
        ->toHaveCount(1);
    expect(validationFailuresFor(new ValidTeamInvitation(null), $acceptedInvitation))
        ->toHaveCount(1);
    expect(validationFailuresFor(new ValidTeamInvitation($user), $acceptedInvitation))
        ->toHaveCount(1);
});
