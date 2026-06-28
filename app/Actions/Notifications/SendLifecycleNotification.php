<?php

namespace App\Actions\Notifications;

use App\Enums\BookingStatus;
use App\Enums\DisputeStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\NotificationDelivery;
use App\Models\Payout;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SendLifecycleNotification
{
    public function __construct(
        private readonly SendNotificationDelivery $sendNotificationDelivery,
    ) {}

    /**
     * @return array<int, NotificationDelivery>
     */
    public function bookingStatusChanged(Booking $booking, BookingStatus $status): array
    {
        $label = $this->label($status->value);

        return $this->sendToRecipients(
            recipients: $this->bookingRecipients($booking),
            eventType: NotificationEventType::BookingStatusChanged,
            source: $booking,
            subject: 'Booking '.$label,
            body: "Booking {$booking->tracker_code} is now {$label}.",
            dedupeKey: "booking:{$booking->id}:{$status->value}",
            metadata: ['booking_status' => $status->value],
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function subscriptionActivated(Subscription $subscription): array
    {
        $profile = $subscription->artisanProfile()->with('user')->firstOrFail();
        $user = $profile->user()->firstOrFail();

        return $this->sendToRecipients(
            recipients: [[
                'email' => $user->email,
                'model' => $user,
                'name' => $user->name,
                'phone' => $user->phone_e164,
            ]],
            eventType: NotificationEventType::SubscriptionActivated,
            source: $subscription,
            subject: 'Subscription activated',
            body: "Your Lartisan subscription for {$profile->business_name} is active.",
            dedupeKey: "subscription:{$subscription->id}:activated",
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function subscriptionReminder(Subscription $subscription, int $daysUntilExpiry): array
    {
        $profile = $subscription->artisanProfile()->with('user')->firstOrFail();
        $user = $profile->user()->firstOrFail();

        return $this->sendToRecipients(
            recipients: [[
                'email' => $user->email,
                'model' => $user,
                'name' => $user->name,
                'phone' => $user->phone_e164,
            ]],
            eventType: NotificationEventType::SubscriptionReminder,
            source: $subscription,
            subject: 'Subscription reminder',
            body: "Your Lartisan subscription for {$profile->business_name} expires in {$daysUntilExpiry} days.",
            dedupeKey: "subscription:{$subscription->id}:reminder:{$daysUntilExpiry}",
            metadata: ['days_until_expiry' => $daysUntilExpiry],
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function payoutStatusChanged(Payout $payout): array
    {
        $profile = $payout->artisanProfile()->with('user')->firstOrFail();
        $user = $profile->user()->firstOrFail();
        $label = $this->label($payout->status->value);

        return $this->sendToRecipients(
            recipients: [[
                'email' => $user->email,
                'model' => $user,
                'name' => $user->name,
                'phone' => $user->phone_e164,
            ]],
            eventType: NotificationEventType::PayoutStatusChanged,
            source: $payout,
            subject: 'Payout '.$label,
            body: "Your payout request for {$payout->currency_code} {$this->minorAmount($payout->amount)} is {$label}.",
            dedupeKey: "payout:{$payout->id}:{$payout->status->value}",
            metadata: ['payout_status' => $payout->status->value],
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function reviewSubmitted(Review $review): array
    {
        $profile = $review->artisanProfile()->with('user')->firstOrFail();
        $user = $profile->user()->firstOrFail();

        return $this->sendToRecipients(
            recipients: [[
                'email' => $user->email,
                'model' => $user,
                'name' => $user->name,
                'phone' => $user->phone_e164,
            ]],
            eventType: NotificationEventType::ReviewSubmitted,
            source: $review,
            subject: 'New verified review',
            body: "A customer left a {$review->rating}-star verified review for {$profile->business_name}.",
            dedupeKey: "review:{$review->id}:submitted",
            metadata: ['rating' => $review->rating],
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function disputeOpened(Dispute $dispute): array
    {
        return $this->disputeNotification(
            dispute: $dispute,
            eventType: NotificationEventType::DisputeOpened,
            subject: 'Dispute opened',
            body: "A dispute was opened: {$dispute->subject}.",
            dedupeKey: "dispute:{$dispute->id}:opened",
            status: $dispute->status,
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function disputeEscalated(Dispute $dispute): array
    {
        return $this->disputeNotification(
            dispute: $dispute,
            eventType: NotificationEventType::DisputeEscalated,
            subject: 'Dispute escalated',
            body: "Dispute {$dispute->subject} has been escalated for state review.",
            dedupeKey: "dispute:{$dispute->id}:escalated",
            status: $dispute->status,
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function disputeResolved(Dispute $dispute): array
    {
        return $this->disputeNotification(
            dispute: $dispute,
            eventType: NotificationEventType::DisputeResolved,
            subject: 'Dispute resolved',
            body: "Dispute {$dispute->subject} has been resolved.",
            dedupeKey: "dispute:{$dispute->id}:resolved",
            status: $dispute->status,
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function supportCaseOpened(SupportCase $supportCase): array
    {
        return $this->supportCaseNotification(
            supportCase: $supportCase,
            eventType: NotificationEventType::SupportCaseOpened,
            subject: 'Support case opened',
            body: "Support case {$supportCase->subject} is open.",
            dedupeKey: "support:{$supportCase->id}:opened",
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function supportCaseResolved(SupportCase $supportCase): array
    {
        return $this->supportCaseNotification(
            supportCase: $supportCase,
            eventType: NotificationEventType::SupportCaseResolved,
            subject: 'Support case resolved',
            body: "Support case {$supportCase->subject} has been resolved.",
            dedupeKey: "support:{$supportCase->id}:resolved",
        );
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    public function sendSubscriptionReminders(int $daysUntilExpiry = 7): array
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereDate('ends_at', now()->addDays($daysUntilExpiry)->toDateString())
            ->get()
            ->flatMap(fn (Subscription $subscription): array => $this->subscriptionReminder($subscription, $daysUntilExpiry))
            ->all();
    }

    /**
     * @param  array<int, array{model?: Model|null, name?: string|null, email?: string|null, phone?: string|null}>  $recipients
     * @param  array<string, mixed>  $metadata
     * @return array<int, NotificationDelivery>
     */
    private function sendToRecipients(
        array $recipients,
        NotificationEventType $eventType,
        Model $source,
        string $subject,
        string $body,
        string $dedupeKey,
        array $metadata = [],
    ): array {
        $deliveries = [];

        foreach ($recipients as $recipient) {
            $recipientModel = ($recipient['model'] ?? null) instanceof Model ? $recipient['model'] : null;

            foreach ($this->channels() as $channel) {
                $address = $channel === NotificationChannel::Email
                    ? ($recipient['email'] ?? null)
                    : ($recipient['phone'] ?? null);

                if (! is_string($address) || trim($address) === '') {
                    continue;
                }

                $deliveries[] = $this->sendNotificationDelivery->handle(
                    eventType: $eventType,
                    channel: $channel,
                    recipientAddress: $address,
                    subject: $subject,
                    body: $body,
                    recipient: $recipientModel,
                    source: $source,
                    recipientName: $recipient['name'] ?? null,
                    dedupeKey: $dedupeKey,
                    metadata: $metadata,
                );
            }
        }

        return $deliveries;
    }

    /**
     * @return array<int, array{model?: Model|null, name?: string|null, email?: string|null, phone?: string|null}>
     */
    private function bookingRecipients(Booking $booking): array
    {
        $customer = null;
        $customerEmail = $booking->customer_email;
        $customerName = $booking->customer_name;
        $customerPhone = $booking->customer_phone;

        if ($booking->customer_id !== null) {
            $customer = $booking->customer()->first();

            if ($customer instanceof User) {
                $customerEmail = $customer->email;
                $customerName = $customer->name;
                $customerPhone = $customer->phone_e164 ?? $booking->customer_phone;
            }
        }

        $profile = $booking->artisanProfile()->with('user')->firstOrFail();
        $artisan = $profile->user()->firstOrFail();

        return [
            [
                'email' => $customerEmail,
                'model' => $customer,
                'name' => $customerName,
                'phone' => $customerPhone,
            ],
            [
                'email' => $artisan->email,
                'model' => $artisan,
                'name' => $artisan->name,
                'phone' => $artisan->phone_e164 ?? $profile->public_phone,
            ],
        ];
    }

    /**
     * @return array<int, NotificationChannel>
     */
    private function channels(): array
    {
        $channels = [];

        foreach ((array) config('lartisan.notifications.default_channels', []) as $channel) {
            if (! is_string($channel)) {
                continue;
            }

            $notificationChannel = NotificationChannel::tryFrom($channel);

            if ($notificationChannel instanceof NotificationChannel) {
                $channels[] = $notificationChannel;
            }
        }

        return $channels;
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    private function disputeNotification(
        Dispute $dispute,
        NotificationEventType $eventType,
        string $subject,
        string $body,
        string $dedupeKey,
        DisputeStatus $status,
    ): array {
        $customer = $dispute->customer()->first();
        $profile = $dispute->artisanProfile()->with('user')->firstOrFail();
        $artisan = $profile->user()->firstOrFail();

        return $this->sendToRecipients(
            recipients: [
                [
                    'email' => $customer?->email,
                    'model' => $customer,
                    'name' => $customer?->name,
                    'phone' => $customer?->phone_e164,
                ],
                [
                    'email' => $artisan->email,
                    'model' => $artisan,
                    'name' => $artisan->name,
                    'phone' => $artisan->phone_e164,
                ],
            ],
            eventType: $eventType,
            source: $dispute,
            subject: $subject,
            body: $body,
            dedupeKey: $dedupeKey,
            metadata: ['dispute_status' => $status->value],
        );
    }

    private function label(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->headline()->toString();
    }

    private function minorAmount(int $amount): string
    {
        return number_format($amount / 100, 2);
    }

    /**
     * @return array<int, NotificationDelivery>
     */
    private function supportCaseNotification(
        SupportCase $supportCase,
        NotificationEventType $eventType,
        string $subject,
        string $body,
        string $dedupeKey,
    ): array {
        $requester = $supportCase->requester()->first();
        $owner = $supportCase->owner()->first();

        return $this->sendToRecipients(
            recipients: [
                [
                    'email' => $requester?->email,
                    'model' => $requester,
                    'name' => $requester?->name,
                    'phone' => $requester?->phone_e164,
                ],
                [
                    'email' => $owner?->email,
                    'model' => $owner,
                    'name' => $owner?->name,
                    'phone' => $owner?->phone_e164,
                ],
            ],
            eventType: $eventType,
            source: $supportCase,
            subject: $subject,
            body: $body,
            dedupeKey: $dedupeKey,
            metadata: ['support_case_status' => $supportCase->status->value],
        );
    }
}
