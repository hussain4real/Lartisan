<?php

namespace App\Actions\Bookings;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\BookingMessageSenderRole;
use App\Enums\BookingStatus;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostBookingMessage
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @return array<int, BookingStatus>
     */
    public static function eligibleStatuses(): array
    {
        return [
            BookingStatus::Requested,
            BookingStatus::Accepted,
            BookingStatus::Paid,
            BookingStatus::Escrowed,
            BookingStatus::InProgress,
        ];
    }

    public static function canSend(Booking $booking): bool
    {
        return $booking->customer_id !== null
            && in_array($booking->status, self::eligibleStatuses(), true);
    }

    public function handle(Booking $booking, User $sender, string $body): BookingMessage
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('A message body is required.'),
            ]);
        }

        return DB::transaction(function () use ($booking, $sender, $body): BookingMessage {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $senderRole = $this->senderRole($booking, $sender);

            if (! self::canSend($booking)) {
                throw ValidationException::withMessages([
                    'body' => __('Chat is only available while a registered booking is pending, accepted, paid, escrowed, or in progress.'),
                ]);
            }

            $message = BookingMessage::query()->create([
                'booking_id' => $booking->id,
                'sender_id' => $sender->id,
                'sender_role' => $senderRole,
                'body' => $body,
                'metadata' => [
                    'source' => 'booking_chat',
                    'booking_status' => $booking->status->value,
                ],
            ]);

            $this->recordAuditLog->handle(
                actor: $sender,
                action: 'booking.message.created',
                subject: $message,
                after: [
                    'booking_id' => $booking->id,
                    'sender_role' => $senderRole->value,
                ],
            );

            return $message->refresh();
        }, attempts: 3);
    }

    public function senderRole(Booking $booking, User $sender): BookingMessageSenderRole
    {
        if ($booking->customer_id === $sender->id) {
            return BookingMessageSenderRole::Customer;
        }

        $profile = $booking->artisanProfile()->first();

        if ($profile instanceof ArtisanProfile && $profile->user_id === $sender->id) {
            return BookingMessageSenderRole::Artisan;
        }

        throw new AuthorizationException('You cannot use chat for this booking.');
    }
}
