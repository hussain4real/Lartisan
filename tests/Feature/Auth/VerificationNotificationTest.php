<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;

test('mail sender defaults use the verified Lartisan domain', function () {
    $mailConfig = file_get_contents(config_path('mail.php'));
    $backupConfig = file_get_contents(config_path('backup.php'));
    $environmentExample = file_get_contents(base_path('.env.example'));

    if (! is_string($mailConfig) || ! is_string($backupConfig) || ! is_string($environmentExample)) {
        throw new RuntimeException('Expected mail configuration files to be readable.');
    }

    expect(str_contains($mailConfig, "env('MAIL_FROM_ADDRESS', 'info@lartisan.app')"))->toBeTrue()
        ->and(str_contains($mailConfig, "env('MAIL_FROM_ADDRESS', 'hello@example.com')"))->toBeFalse()
        ->and(str_contains($backupConfig, "env('MAIL_FROM_ADDRESS', 'info@lartisan.app')"))->toBeTrue()
        ->and(str_contains($backupConfig, "env('MAIL_FROM_ADDRESS', 'hello@example.com')"))->toBeFalse()
        ->and(str_contains($environmentExample, 'MAIL_FROM_ADDRESS="info@lartisan.app"'))->toBeTrue()
        ->and(str_contains($environmentExample, 'MAIL_FROM_ADDRESS="hello@example.com"'))->toBeFalse();
});

test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $html = (string) $mail->render();

        if (! is_string($mail->markdown)) {
            throw new RuntimeException('Expected verification mail to use a Markdown template.');
        }

        $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());

        expect($mail->subject)->toBe('Verify your Lartisan email address')
            ->and($mail->greeting)->toBe("Welcome to Lartisan, {$user->name}")
            ->and($mail->actionText)->toBe('Verify email address')
            ->and($mail->actionUrl)->toContain("/email/verify/{$user->id}/")
            ->and($html)->toContain('images/lartisan-mail-logo.png')
            ->and($html)->toContain('alt="Lartisan"')
            ->and($html)->toContain('width="200"')
            ->and($html)->toContain('Verified local services, booking updates, secure payments, and support with clear accountability.')
            ->and($html)->toContain('background-color: #002172')
            ->and($text)->toContain('Verify email address')
            ->and($text)->toContain($mail->actionUrl)
            ->and($text)->toContain('Verified local services, booking updates, secure payments, and support with clear accountability.');

        return true;
    });
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect('/dashboard');

    Notification::assertNothingSent();
});
