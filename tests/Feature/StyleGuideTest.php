<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutVite;
use function Pest\Laravel\withUnencryptedCookie;

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

test('appearance cookie values are normalized before rendering the style guide shell', function () {
    $user = User::factory()->create();

    withoutVite();
    actingAs($user);

    foreach ([
        'light' => ['light', 'light'],
        'dark' => ['dark', 'dark'],
        'system' => ['system', 'system'],
        'invalid' => ['neon', 'system'],
    ] as [$cookieValue, $expectedAppearance]) {
        withUnencryptedCookie('appearance', $cookieValue);

        get(route('style-guide.edit'))
            ->assertOk()
            ->assertSee("const cookieAppearance = \"{$expectedAppearance}\";", false);
    }
});
