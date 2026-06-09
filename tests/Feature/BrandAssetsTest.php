<?php

use Illuminate\Support\Facades\File;

test('app shell declares Lartisan icon assets', function (): void {
    $appShell = File::get(resource_path('views/app.blade.php'));

    expect($appShell)
        ->toContain('href="/favicon.ico?v=lartisan"')
        ->toContain('href="/favicon.svg?v=lartisan"')
        ->toContain('href="/apple-touch-icon.png?v=lartisan"');
});

test('favicon svg uses the Lartisan brand mark', function (): void {
    $favicon = File::get(public_path('favicon.svg'));

    expect($favicon)
        ->toContain('<title>Lartisan</title>')
        ->toContain('#3E4095');

    expect(str_contains($favicon, '#FF2D20'))->toBeFalse();
});

test('application name fallbacks use Lartisan branding', function (): void {
    expect(File::get(config_path('app.php')))
        ->toContain("'name' => env('APP_NAME', 'Lartisan')");

    expect(File::get(resource_path('views/app.blade.php')))
        ->toContain("config('app.name', 'Lartisan')");

    expect(File::get(resource_path('js/app.ts')))
        ->toContain("import.meta.env.VITE_APP_NAME || 'Lartisan'");
});
