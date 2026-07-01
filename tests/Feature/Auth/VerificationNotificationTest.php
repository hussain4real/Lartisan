<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;

test('mail sender defaults use the verified Lartisan domain', function () {
    expect(file_get_contents(config_path('mail.php')))
        ->toContain("env('MAIL_FROM_ADDRESS', 'info@lartisan.app')")
        ->not->toContain("env('MAIL_FROM_ADDRESS', 'hello@example.com')")
        ->and(file_get_contents(config_path('backup.php')))
        ->toContain("env('MAIL_FROM_ADDRESS', 'info@lartisan.app')")
        ->not->toContain("env('MAIL_FROM_ADDRESS', 'hello@example.com')")
        ->and(file_get_contents(base_path('.env.example')))
        ->toContain('MAIL_FROM_ADDRESS="info@lartisan.app"')
        ->not->toContain('MAIL_FROM_ADDRESS="hello@example.com"');
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
