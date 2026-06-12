<?php

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('auth/Register')
            ->where('registrationIntent', 'customer'));
});

test('registration screen can be rendered with artisan intent', function () {
    $response = $this->get(route('register', ['intent' => 'artisan']));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('auth/Register')
            ->where('registrationIntent', 'artisan'));
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    $response->assertRedirect(route('dashboard'));
});

test('new users registering as artisans continue to onboarding', function () {
    $response = $this->post(route('register.store', ['intent' => 'artisan']), [
        'name' => 'Test Artisan',
        'email' => 'artisan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'intent' => 'artisan',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'artisan@example.com')->firstOrFail();

    $response->assertRedirect(route('artisan.onboarding.create', [
        'current_team' => $user->currentTeam,
    ]));
});

test('new users registering as artisans ignore stale intended urls', function () {
    Session::put('url.intended', route('marketplace.index'));

    $response = $this->post(route('register.store', ['intent' => 'artisan']), [
        'name' => 'Stale Intent Artisan',
        'email' => 'stale-artisan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'intent' => 'artisan',
    ]);

    $user = User::where('email', 'stale-artisan@example.com')->firstOrFail();

    $response->assertRedirect(route('artisan.onboarding.create', [
        'current_team' => $user->currentTeam,
    ]));
});
