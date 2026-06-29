# Platform Operations And Recovery

Status: current through Phase 16 implementation.

This process explains reporting, health checks, observability, backup, restore-readiness, retention, and operational recovery.

## Reporting

- Report snapshots are scoped by role and geography.
- Super Admin sees global report snapshots.
- State Coordinator sees state-scoped reporting.
- LGA Admin sees LGA-scoped reporting.
- Area Agent sees assigned-territory operational context.
- Report documents are private operations artifacts unless explicitly exported through authorized workflows.

## Health Dashboard

Super Admin health visibility includes:

- Paystack payment readiness.
- Payout provider readiness.
- Email channel configuration.
- WhatsApp channel configuration.
- Storage and private media readiness.
- Database touchpoints.
- Queue pending and failed-job signals.
- Scheduler freshness.
- Backup status and restore-test freshness.
- Laravel logs and Nightwatch handoff expectations.

## Recovery

- Failed jobs should be reviewed with enough context to distinguish application failures from provider or infrastructure failures.
- Provider failures should create visible operational signals rather than silent retries.
- Restore tests should be recorded for database and critical media readiness.
- Recovery notes should identify actor, action, result, and follow-up owner.

## Backup And Retention

- Critical database and media backups use the configured backup system.
- Audit logs, payment records, KYC records, wallet ledgers, admin actions, review records, payout records, and dispute outcomes are retained conservatively by default.
- Notification delivery logs, provider callback logs, health snapshots, and operational noise can be pruned after configured retention windows.
- Retention changes should be treated as policy decisions and tested before rollout.

## Operational Rules

- Use Laravel Nightwatch and Laravel logs as approved monitoring sources.
- Never use direct database edits as the normal recovery path for wallet, payout, KYC, or dispute outcomes.
- Record manual overrides with a reason and enough context for later audit.
- Prefer idempotent commands and provider verification when retrying payment or payout work.
