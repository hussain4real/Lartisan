<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\Bookings\PostBookingMessage;
use App\Enums\BookingMessageSenderRole;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\ServiceCategory;
use App\Models\User;

trait BuildsBookingChatPayload
{
    /**
     * @return array{
     *     role: string,
     *     backUrl: string,
     *     currentTeamSlug: string|null,
     *     canSend: bool,
     *     privacyNotice: string,
     *     booking: array{id: int, trackerCode: string, status: string, customerName: string, artisan: array{id: int, businessName: string}, service: array{id: int, title: string, category: string}|null},
     *     messages: array<int, array{id: int, senderRole: string, senderName: string, body: string, mine: bool, createdAt: string|null}>
     * }
     */
    protected function bookingChatPayload(
        Booking $booking,
        User $viewer,
        BookingMessageSenderRole $viewerRole,
        string $backUrl,
        ?string $currentTeamSlug = null,
    ): array {
        $booking->loadMissing(['artisanProfile', 'artisanService.category', 'messages.sender']);

        $profile = $booking->artisanProfile;
        $service = $booking->artisanService;

        assert($profile instanceof ArtisanProfile);

        return [
            'role' => $viewerRole->value,
            'backUrl' => $backUrl,
            'currentTeamSlug' => $currentTeamSlug,
            'canSend' => PostBookingMessage::canSend($booking),
            'privacyNotice' => __('For privacy and safety, keep phone numbers, email addresses, links, and off-platform contact details out of chat.'),
            'booking' => [
                'id' => $booking->id,
                'trackerCode' => $booking->tracker_code,
                'status' => $booking->status->value,
                'customerName' => $booking->customer_name,
                'artisan' => [
                    'id' => $profile->id,
                    'businessName' => $profile->business_name,
                ],
                'service' => $service instanceof ArtisanService ? $this->chatServicePayload($service) : null,
            ],
            'messages' => $booking->messages
                ->sortBy('created_at')
                ->values()
                ->map(fn (BookingMessage $message): array => [
                    'id' => $message->id,
                    'senderRole' => $message->sender_role->value,
                    'senderName' => $this->senderName($message, $booking, $profile),
                    'body' => $message->body,
                    'mine' => $message->sender_id === $viewer->id,
                    'createdAt' => $message->created_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, title: string, category: string}
     */
    private function chatServicePayload(ArtisanService $service): array
    {
        $category = $service->category;

        assert($category instanceof ServiceCategory);

        return [
            'id' => $service->id,
            'title' => $service->title,
            'category' => $category->name,
        ];
    }

    private function senderName(BookingMessage $message, Booking $booking, ArtisanProfile $profile): string
    {
        return match ($message->sender_role) {
            BookingMessageSenderRole::Customer => $booking->customer_name,
            BookingMessageSenderRole::Artisan => $profile->business_name,
        };
    }
}
