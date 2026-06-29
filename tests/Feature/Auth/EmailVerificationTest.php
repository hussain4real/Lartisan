<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('user accounts require email verification', function () {
    expect(User::factory()->make())->toBeInstanceOf(MustVerifyEmail::class);
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->teams()->where('is_personal', true)->firstOrFail();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);
    $freshUser = User::query()->findOrFail($user->id);

    Event::assertDispatched(Verified::class);
    expect($freshUser->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect("/{$team->slug}/dashboard?verified=1");
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);
    $freshUser = User::query()->findOrFail($user->id);

    Event::assertNotDispatched(Verified::class);
    expect($freshUser->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl);
    $freshUser = User::query()->findOrFail($user->id);

    Event::assertNotDispatched(Verified::class);
    expect($freshUser->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect('/dashboard');
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();
    $team = $user->teams()->where('is_personal', true)->firstOrFail();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect("/{$team->slug}/dashboard?verified=1");
    $freshUser = User::query()->findOrFail($user->id);

    Event::assertNotDispatched(Verified::class);
    expect($freshUser->hasVerifiedEmail())->toBeTrue();
});

test('unverified users are redirected from protected application routes', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->teams()->where('is_personal', true)->firstOrFail();

    $routes = [
        route('dashboard', ['current_team' => $team]),
        route('artisan.onboarding.create', ['current_team' => $team]),
        route('customer.bookings.index'),
        route('identity.phone.edit'),
        route('security.edit'),
        route('teams.index'),
    ];

    foreach ($routes as $protectedRoute) {
        $this
            ->actingAs($user)
            ->get($protectedRoute)
            ->assertRedirect(route('verification.notice'));
    }
});

test('unverified users can still browse public marketplace and correct profile email', function () {
    $user = User::factory()->unverified()->create();

    $this->get(route('marketplace.index'))->assertOk();

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});
