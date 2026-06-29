# Super Admin Guide

Primary surface: `/admin`

## Purpose

Super Admins govern the whole Lartisan platform. They own global access, risk policy, platform configuration, financial oversight, and final escalation decisions.

## Current Responsibilities

- Maintain platform roles and permissions through seeded access templates.
- Manage global reason codes used for KYC decisions, territory reassignment, and suspensions.
- View and moderate artisan profiles across all states, LGAs, and territories.
- View all KYC queues and high-risk or escalated verification records.
- View all area agent assignments and territory coverage.
- Review audit logs for sensitive operational actions.
- Oversee payment, subscription, wallet, payout, and ledger data.
- Review and resolve dispute queues across all states, LGAs, and territories.
- Approve and process payout requests, including failed-attempt recovery.
- Generate global report snapshots and render report documents.
- Monitor provider health, queues, failed jobs, backups, restore tests, scheduler freshness, and recovery signals.

## Standard Workflow

1. Sign in with a Super Admin account.
2. Open `/admin`.
3. Review KYC queue health across the platform.
4. Check reason codes before operations teams begin a new campaign or policy change.
5. Audit escalated or unusual verification decisions.
6. Review territory coverage gaps or repeated reassignment patterns.
7. Coordinate finance follow-up for payment, wallet, payout, or subscription anomalies.
8. Review dispute, payout, support, health, and report resources in the operations panel.
9. Approve payout requests, monitor automated payout batches, reconcile exceptions, or use audited manual fallback where policy allows.

## Decision Rules

- Create or change global policies only when they can be applied consistently across states.
- Use reason codes that match the operation category.
- Do not edit wallet ledger entries. Require adjustment entries for corrections.
- Treat payment webhook data as provider evidence, not as a user-editable transaction source.
- Process payouts only from verified payout accounts and sufficient available wallet balance.
- Use dispute resolution notes for auditability, especially when hiding a linked review.

## Escalations Owned

- Cross-state fraud or duplicate artisan patterns.
- Policy exceptions for verification, suspension, or reinstatement.
- Finance disputes that require platform-level review.
- Role or permission changes.
- Final decisions on severe operational incidents.

## Process Runbooks

- [Artisan verification and activation](../processes/artisan-verification-and-activation.md)
- [Finance operations](../processes/finance-operations.md)
- [Communications and support operations](../processes/communications-and-support-operations.md)
- [Platform operations and recovery](../processes/platform-operations-and-recovery.md)

## Current Limitations

- Subscription plan management is seeded in code; a Super Admin plan-management UI is not implemented yet.
- Booking exceptions should be handled through booking, dispute, and support records rather than a separate booking-exception panel.
