# State Coordinator Guide

Primary surface: `/state`

## Purpose

State Coordinators supervise Lartisan operations within one state. They focus on LGA performance, escalated KYC, state-level risk patterns, and state reporting.

## Current Responsibilities

- View artisan profiles scoped to the coordinator's state.
- View KYC submissions scoped to the state.
- Review escalated or high-risk KYC cases.
- Monitor LGA Admin and Area Agent performance through scoped queues.
- Review area assignments in the state.
- Moderate artisan profiles within state scope.
- View state-level reporting permissions where available.
- Review and resolve scoped dispute queues for severe or escalated cases.
- View scoped payout records where finance visibility is allowed.
- Generate state report snapshots.
- Monitor scoped support cases and review moderation queues where policy allows.

## Standard Workflow

1. Sign in with a State Coordinator account.
2. Open `/state`.
3. Review escalated KYC records first.
4. Check local KYC queues for LGAs with delays or repeated returns.
5. Review artisan profile suspensions or suspicious verification changes.
6. Work with LGA Admins to rebalance area coverage.
7. Escalate platform-wide risk, finance, or policy issues to Super Admin.
8. Review escalated disputes and state report snapshots.

## KYC Review Rules

- Use State Coordinator review for escalated, disputed, duplicate, or high-risk cases.
- Require a valid KYC decision reason code.
- Leave clear notes when returning, rejecting, approving, or escalating records.
- Confirm field evidence and LGA review notes before overriding a local decision.

## Escalations Owned

- KYC cases escalated by LGA Admins.
- Multi-LGA fraud patterns.
- State-level agent or LGA performance issues.
- Severe local disputes that exceed one LGA's authority.
- Repeated support cases or review disputes that show statewide policy risk.

## Process Runbooks

- [Artisan verification and activation](../processes/artisan-verification-and-activation.md)
- [Booking trust lifecycle](../processes/booking-trust-lifecycle.md)
- [Communications and support operations](../processes/communications-and-support-operations.md)
- [Platform operations and recovery](../processes/platform-operations-and-recovery.md)

## Current Limitations

- Booking exceptions should be handled through booking, dispute, and support records rather than a separate booking-exception panel.
- Customer support cases are triaged through the scoped support inbox.
- State finance dashboards are scoped to current payment/payout visibility and report snapshots.
