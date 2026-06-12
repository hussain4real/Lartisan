<?php

use Inertia\Testing\AssertableInertia as Assert;

test('guests can view public legal pages', function (string $routeName, string $component): void {
    $this->withoutVite();

    $this
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component($component));
})->with([
    'privacy policy' => ['privacy-policy', 'Legal/PrivacyPolicy'],
    'terms of service' => ['terms-of-service', 'Legal/TermsOfService'],
]);
