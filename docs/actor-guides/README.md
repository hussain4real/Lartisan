# Lartisan Actor Guides

Status: current through Phase 7 implementation

These guides explain how each Lartisan actor should use the platform, what they own, what they can see, and where work should be escalated. They are operational guides, not implementation specs.

## Actor Guides

| Actor | Guide | Primary surface |
| --- | --- | --- |
| Super Admin | [Super Admin Guide](super-admin.md) | `/admin` Filament panel |
| State Coordinator | [State Coordinator Guide](state-coordinator.md) | `/state` Filament panel |
| Local Government Admin | [Local Government Admin Guide](local-government-admin.md) | `/lga` Filament panel |
| Area Agent | [Area Agent Guide](area-agent.md) | `/agent` Filament panel |
| Artisan | [Artisan Guide](artisan.md) | Artisan workspace under `/{team}/artisan` |
| Registered Customer | [Registered Customer Guide](registered-customer.md) | Marketplace and `/customer/bookings` |
| Guest Customer | [Guest Customer Guide](guest-customer.md) | Marketplace and secure booking tracker links |

## Current Implementation Boundary

The platform currently includes:

- Identity, phone verification, account claiming, teams, and artisan workspaces.
- Geography, area assignments, role seeds, scoped visibility, and audit logging.
- Artisan profile, service catalog, KYC intake, portfolio media, and field visits.
- Operations verification panels for KYC review, territory assignment, reason codes, and suspensions.
- Subscription plans, Paystack checkout initiation, payment webhooks, subscription activation, wallets, immutable ledger entries, and payout account records.
- Marketplace discovery for verified, approved, active subscribed artisans.
- Guest and registered booking request creation with address snapshots and optional booking attachments.
- Secure booking tracker links, customer booking screens, artisan booking queues, booking status history, and completion wallet release.
- Verified customer reviews for confirmed paid bookings.
- Customer and artisan dispute intake against bookings and linked reviews, private dispute evidence, audit logs, and automatically created support cases.
- Artisan payout requests from verified payout accounts, operations payout approval, manual processing, failed-attempt retries, and immutable wallet payout debits.
- Scoped report snapshots, report PDF rendering, and Filament dispute, payout, and report resources.

Booking checkout/escrow, chat, guest review submission, notification templates, dedicated support inboxes, and operations booking exception queues are planned later phases. The customer guides describe current booking behavior and label not-yet-built flows clearly.

## Phase 6-7 Booking And Trust Surfaces

| Surface | Purpose |
| --- | --- |
| `/marketplace` | Search verified subscribed artisans by keyword, service category, and geography. |
| `/marketplace/artisans/{artisanProfile}` | View a public artisan profile, services, location, availability, and portfolio. |
| `/marketplace/artisans/{artisanProfile}/book` | Create a guest or registered booking request. |
| `/booking-tracker/{trackerCode}?token=...` | Secure tracker for a single booking context. |
| `/customer/bookings` | Registered customer booking list. |
| `/customer/bookings/{booking}` | Registered customer booking detail and completion confirmation when eligible. |
| `POST /customer/bookings/{booking}/reviews` | Submit a verified review for an eligible confirmed paid booking. |
| `/customer/bookings/{booking}/disputes/create` | Open a customer dispute for an owned booking. |
| `/{team}/artisan/bookings` | Artisan booking queue for accept, reject, start, and finish actions. |
| `/{team}/artisan/bookings/{booking}/disputes/create` | Open an artisan dispute for a booking in the artisan workspace. |
| `/{team}/artisan/wallet` | View wallet balances, ledger entries, payout accounts, and payout requests. |
| `POST /{team}/artisan/wallet/payouts` | Request a payout from available wallet balance using a verified payout account. |
| `/admin`, `/state`, `/lga`, `/agent` | Scoped Filament operations panels for disputes, payouts where permitted, and reports. |

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
- Booking status histories are append-only. Actors should move bookings through the supported lifecycle instead of editing final states directly.
- Sensitive identity, KYC, payment, and payout details should be handled only inside the app and only by actors with a legitimate operational reason.
