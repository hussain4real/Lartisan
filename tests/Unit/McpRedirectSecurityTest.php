<?php

use Illuminate\Http\Request;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Tests\TestCase;

uses(TestCase::class);

test('MCP rejects userinfo and loopback prefix redirect bypasses', function (string $redirect): void {
    config(['mcp.redirect_domains' => ['http://localhost']]);

    $response = (new OAuthRegisterController)(Request::create('/oauth/register', 'POST', [
        'redirect_uris' => [$redirect],
    ]));

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'])->toBe('invalid_redirect_uri');
})->with([
    'http://localhost@attacker.example/callback',
    'http://127.0.0.1@attacker.example/callback',
    'http://127.0.0.1.attacker.example/callback',
    'http://localhost.attacker.example/callback',
    'http://[::1]@attacker.example/callback',
]);

test('MCP preserves valid loopback and custom scheme redirects', function (string $redirect): void {
    config([
        'mcp.redirect_domains' => ['http://localhost'],
        'mcp.custom_schemes' => ['mcp-client'],
    ]);

    $response = (new OAuthRegisterController)(Request::create('/oauth/register', 'POST', [
        'redirect_uris' => [$redirect],
    ]));

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['error_description'])->toBe('OAuth support (Passport) is not installed.');
})->with([
    'http://localhost:54321/callback',
    'http://127.0.0.1:54321/callback',
    'http://[::1]:54321/callback',
    'mcp-client://callback',
]);
