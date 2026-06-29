# Communications And Support Operations

Status: current through Phase 16 implementation.

This process explains customer/artisan notifications, branded mail, WhatsApp delivery, support inboxes, review moderation, and privacy rules.

## Notifications

- Email and WhatsApp notifications are sent through provider abstractions.
- Notification delivery records are the audit source for channel, provider, attempts, failure reason, callback metadata, and dead-letter state.
- Emails use the shared Lartisan Markdown mail theme.
- Sensitive payout, dispute, and support messages stay concise and direct users back to Lartisan rather than exposing private evidence or account details.

## Common Events

- Email verification and password reset.
- Waitlist confirmation.
- Team invitation.
- Booking requested, accepted, rejected, started, finished, paid, settled, reviewed, or disputed.
- Subscription payment, renewal, grace, and expiry.
- Payout paid, failed, retrying, uncertain, action-required, or reversed.
- Support case opened, assigned, noted, escalated, or closed.

## Support Inbox

- Support cases can link booking, artisan, payment, payout, review, profile, or dispute context where available.
- Operations users see support cases according to scope.
- Internal notes are private to operations users.
- Assignment and status changes are audited.
- Dispute-driven support cases should include enough context to act, but should not duplicate private media into public channels.

## Review Moderation

- Suspicious review signals can route reviews into moderation.
- Moderators can review proof, hide or restore review visibility where authorized, and preserve audit history.
- Artisan review responses are allowed through the supported workflow and remain tied to the review.

## Privacy Rules

- Keep KYC, payment, payout account, dispute, and support evidence inside the app.
- Do not include bank account numbers, recipient codes, identity documents, or private dispute proof in outbound email or WhatsApp bodies.
- Prefer status, tracking references, and links back to Lartisan for detailed follow-up.
