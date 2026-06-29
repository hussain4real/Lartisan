# Booking Trust Lifecycle

Status: current through Phase 16 implementation.

This process explains how bookings move from marketplace request to completion, review, dispute, and support handling.

## Booking Creation

- Guests and registered customers can create booking requests from public marketplace artisan profiles.
- Booking requests include contact details, service address snapshot, schedule, notes, and optional attachments.
- Guest bookings use secure tracker links and OTP-supported identity flows.
- Registered customers can use saved addresses and customer booking screens.

## Booking Status Flow

| Status | Owner action |
| --- | --- |
| Requested | Customer submits booking; artisan can accept or reject. |
| Accepted | Artisan accepts and can start work. |
| Paid | Customer payment succeeds where booking checkout applies. |
| Escrowed | Booking funds are held for settlement after completion. |
| InProgress | Artisan starts the work. |
| Finished | Artisan marks work finished and waits for customer confirmation. |
| Confirmed | Customer or secure tracker confirms completion. |
| Settled | Wallet credit is released according to escrow and settlement rules. |
| Reviewed | Eligible customer review has been submitted. |
| Rejected or Cancelled | Booking closes without normal completion. |
| Disputed | Booking or linked review is under operations review. |

## Chat

- Booking chat is available for registered customer bookings while the booking is requested, accepted, paid, escrowed, or in progress.
- Closed booking chats remain readable but not writable.
- Contact-detail safeguards block unsafe off-platform contact sharing where configured.
- Guests use tracker links and support/dispute flows instead of persistent in-app chat.

## Reviews

- Customers can leave one verified review after an eligible confirmed paid booking.
- Review proof media is supported.
- Artisans can respond to reviews.
- Suspicious reviews can be routed to moderation and support workflows.

## Disputes

- Customers and artisans can open disputes for eligible bookings.
- Disputes can target booking, review, profile, or payment contexts where implemented.
- Dispute evidence is private to involved parties and authorized operations users.
- Money-changing dispute outcomes must create audited immutable wallet ledger adjustments.

## Guardrails

- Booking status histories are append-only.
- Wallet release is idempotent; repeated release attempts return the existing ledger entry.
- Reviews are tied to real eligible bookings.
- Support and dispute notes should be concise and should not expose unrelated private evidence.
