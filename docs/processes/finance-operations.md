# Finance Operations

Status: current through Phase 16 implementation.

This process explains subscriptions, payments, escrow, wallet ledgers, payout requests, automated payout batches, reconciliation, and finance exceptions.

## Subscription And Booking Payments

- Artisan subscriptions are paid through Paystack checkout.
- Successful subscription webhooks activate subscriptions idempotently.
- Booking checkout stores provider references, payment snapshots, commission snapshots, provider-fee snapshots, and booking payment lifecycle timestamps.
- Paystack webhooks are signature-verified and idempotent.
- Refunds and adjustments create auditable wallet or payment records rather than overwriting prior history.

## Wallet Ledger

- Wallet ledger entries are immutable.
- Booking completion posts booking-credit entries once.
- Payout approval reserves funds through payout-debit entries once.
- Corrections use adjustment entries with immutable references.
- Manual edits to wallet balances are not part of normal operations.

## Payout Account Verification

- Payout accounts store sensitive account identifiers securely.
- Paystack bank resolve verifies account details.
- Transfer recipient creation stores the provider recipient code needed for automated transfers.
- Rejected or unresolved accounts are moved to finance review rather than retried blindly.

## Payout Dispatch

1. Artisan requests payout from available balance using a verified payout account.
2. Finance approves an eligible payout.
3. Approval reserves funds with a payout debit once.
4. Scheduled or manual dispatch groups approved/retrying payouts into a payout batch.
5. Paystack individual transfer is initiated with idempotent references.
6. Processing, uncertain, action-required, failed, reversed, and successful outcomes are reconciled through webhooks and polling.

## Reconciliation

- Paystack transfer webhooks handle `transfer.success`, `transfer.failed`, and `transfer.reversed`.
- `payouts:reconcile-processing` verifies stale processing or uncertain transfers.
- Duplicate provider events do not duplicate payment or wallet effects.
- OTP-required or ambiguous provider responses move to finance review instead of auto-retrying.

## Exceptions

- Terminal transfer failure releases the reserved payout debit once with an immutable adjustment ledger entry and opens a finance/support case.
- Transfer reversal after payment marks the payout adjusted, creates an adjustment credit, and opens a finance exception case.
- Missing debit, action-required, uncertain, failed, and reversed states appear in finance exception queues.
- Artisan payout visibility shows status, provider status, tracking references, and lifecycle timestamps without exposing recipient codes or sensitive bank details.
