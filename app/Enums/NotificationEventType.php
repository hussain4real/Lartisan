<?php

namespace App\Enums;

enum NotificationEventType: string
{
    case BookingStatusChanged = 'booking.status_changed';
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionReminder = 'subscription.reminder';
    case PayoutStatusChanged = 'payout.status_changed';
    case ReviewSubmitted = 'review.submitted';
    case DisputeOpened = 'dispute.opened';
    case DisputeEscalated = 'dispute.escalated';
    case DisputeResolved = 'dispute.resolved';
    case SupportCaseOpened = 'support_case.opened';
    case SupportCaseResolved = 'support_case.resolved';
    case ProviderCallback = 'provider.callback';
}
