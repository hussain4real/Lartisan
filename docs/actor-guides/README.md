# Lartisan Actor Guides

Status: current through Phase 5 implementation

These guides explain how each Lartisan actor should use the platform, what they own, what they can see, and where work should be escalated. They are operational guides, not implementation specs.

## Actor Guides

| Actor | Guide | Primary surface |
| --- | --- | --- |
| Super Admin | [Super Admin Guide](super-admin.md) | `/admin` Filament panel |
| State Coordinator | [State Coordinator Guide](state-coordinator.md) | `/state` Filament panel |
| Local Government Admin | [Local Government Admin Guide](local-government-admin.md) | `/lga` Filament panel |
| Area Agent | [Area Agent Guide](area-agent.md) | `/agent` Filament panel |
| Artisan | [Artisan Guide](artisan.md) | Artisan workspace under `/{team}/artisan` |
| Registered Customer | [Registered Customer Guide](registered-customer.md) | Customer workspace, future booking surfaces |
| Guest Customer | [Guest Customer Guide](guest-customer.md) | Guest booking and secure links, future booking surfaces |

## Current Implementation Boundary

The platform currently includes:

- Identity, phone verification, account claiming, teams, and artisan workspaces.
- Geography, area assignments, role seeds, scoped visibility, and audit logging.
- Artisan profile, service catalog, KYC intake, portfolio media, and field visits.
- Operations verification panels for KYC review, territory assignment, reason codes, and suspensions.
- Subscription plans, Paystack checkout initiation, payment webhooks, subscription activation, wallets, immutable ledger entries, and payout account records.

Customer booking, chat, disputes, reviews, payout processing queues, notification templates, and finance operations panels are planned later phases. The customer guides describe target behavior but label not-yet-built flows clearly.

## Role Hierarchy

| Level | Actor | Scope |
| --- | --- | --- |
| 1 | Super Admin | Platform-wide |
| 2 | State Coordinator | One state |
| 3 | Local Government Admin | One Local Government Area |
| 4 | Area Agent | One or more territories inside an LGA |
| 5 | Artisan | Own business profile, services, KYC, subscription, wallet |
| 6 | Registered Customer | Own profile, addresses, bookings, payments, reviews |
| 7 | Guest Customer | One limited booking context |

## Shared Operating Rules

- Use platform records as the source of truth. Avoid off-platform decisions for KYC, suspension, payments, or support.
- Every KYC decision, territory reassignment, and suspension must use the correct reason code.
- Respect scope boundaries. A state user should not act on another state; an LGA user should not act on another LGA; an agent should stay inside assigned territories.
- Finance and wallet records are append-only where ledger entries are involved. Corrections must be posted as adjustment entries, not by editing history.
- Sensitive identity, KYC, payment, and payout details should be handled only inside the app and only by actors with a legitimate operational reason.

