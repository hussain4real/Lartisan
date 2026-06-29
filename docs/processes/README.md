# Lartisan Process Runbooks

Status: current through Phase 16 implementation.

These runbooks document how Lartisan operates today. Use them for day-to-day product, support, finance, and operations decisions. The BRS, technical spec, roadmap, and implementation progress documents remain the source artifacts for why the system exists and how it was built.

## Runbooks

| Process | Use this when |
| --- | --- |
| [Artisan Verification And Activation](artisan-verification-and-activation.md) | Explaining KYC, field visits, approval, activation, suspension, and verification status. |
| [Marketplace Discovery And Listing Visibility](marketplace-discovery-and-listing-visibility.md) | Explaining public listing eligibility, proximity search, manual geography filters, and seeded coordinates. |
| [Booking Trust Lifecycle](booking-trust-lifecycle.md) | Explaining guest/registered bookings, chat, payment, completion, reviews, and disputes. |
| [Finance Operations](finance-operations.md) | Explaining subscription payments, escrow, wallet ledgers, payout requests, Paystack transfer batches, reconciliation, and exceptions. |
| [Communications And Support Operations](communications-and-support-operations.md) | Explaining email/WhatsApp notifications, support inboxes, review moderation, disputes, and privacy boundaries. |
| [Platform Operations And Recovery](platform-operations-and-recovery.md) | Explaining health checks, reports, observability, backups, restore tests, retention, and operational recovery. |

## Shared Rules

- Platform records are the source of truth for KYC, bookings, payments, wallet entries, payouts, support, and dispute outcomes.
- Sensitive KYC, payment, payout, dispute, and support evidence stays inside Lartisan and is only visible to authorized actors.
- KYC decisions, suspensions, territory reassignments, payout adjustments, dispute outcomes, and operational overrides require clear reason codes or notes.
- Wallet ledger entries are immutable. Corrections are posted as adjustment entries.
- Booking and verification status histories are append-only. Actors should move records through supported workflow actions instead of editing final states directly.
- Precise customer browser coordinates used for marketplace proximity search are query-only and are not persisted to customer, profile, or session records.
