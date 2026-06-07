<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('style guide page can be rendered by authenticated users', function () {
    $user = User::factory()->create();

    $this->withoutVite();

    $this
        ->actingAs($user)
        ->get(route('style-guide.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/StyleGuide'),
        );
});

test('style guide page redirects guests to login', function () {
    $this
        ->get(route('style-guide.edit'))
        ->assertRedirect(route('login'));
});

test('appearance cookie values are normalized before rendering the style guide shell', function (string $cookieValue, string $expectedAppearance) {
    $user = User::factory()->create();

    $this->withoutVite();

    $this
        ->actingAs($user)
        ->withUnencryptedCookie('appearance', $cookieValue)
        ->get(route('style-guide.edit'))
        ->assertOk()
        ->assertSee("const cookieAppearance = \"{$expectedAppearance}\";", false);
})->with([
    'light' => ['light', 'light'],
    'dark' => ['dark', 'dark'],
    'system' => ['system', 'system'],
    'invalid' => ['neon', 'system'],
]);
