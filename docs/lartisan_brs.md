**BUSINESS REQUIREMENTS SPECIFICATION**

**Lartisan App - Premium Artisan Management & Services Marketplace**

**Version:** 2.0 - Overhauled draft

**Date:** 23 May 2026

**Status:** Working BRS for product, operations, and development alignment

**Primary change:** Introduces Local Government Admin as the operational tier between State Coordinator and Area Agent

**Source:** Rebuilt from the existing Lartisan BRS PDF and revised admin-scope assumptions

**Operating-model decision**

A Local Government Area is large enough to require its own administrator. The revised model uses State Coordinator -> Local Government Admin -> Area Agent, where Area Agents are scoped to wards, communities, markets, estates, or service clusters inside an LGA.

| **Document section**        | **Purpose**                                                                          |
| --------------------------- | ------------------------------------------------------------------------------------ |
| Business vision             | Defines the marketplace problem, solution, and target outcomes.                      |
| Stakeholders and roles      | Clarifies customer, artisan, and multi-tier administration responsibilities.         |
| Core journeys               | Documents onboarding, verification, booking, payment, dispute, and payout flows.     |
| Functional requirements     | Lists the capabilities needed by module and user group.                              |
| Non-functional requirements | Sets expectations for security, performance, scalability, usability, and compliance. |

# Document Control

| **Item**         | **Detail**                                                                                                                   |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Document title   | Lartisan App Business Requirements Specification                                                                             |
| Document owner   | Product and Operations                                                                                                       |
| Intended readers | Founders, product stakeholders, engineering, operations, field managers, support, and finance                                |
| Document purpose | Define the business capabilities, role hierarchy, workflows, and acceptance criteria required to build and operate Lartisan. |
| Out of scope     | Detailed UI wireframes, database schema, API contract, implementation sprint plan, and final legal policy wording.           |

## Revision Summary

| **Area**               | **Previous direction**                                                                                                      | **Revised direction**                                                                                                                               |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin hierarchy        | Area Agent was treated as LGA-scoped and carried onboarding, support, verification, and local operations for the whole LGA. | Local Government Admin owns each LGA. Area Agents are smaller field operators assigned to wards, communities, markets, or clusters inside the LGA.  |
| Verification authority | State Coordinator and Area Agent shared most review activity, creating an operational gap between state and field.          | Area Agent performs physical checks. LGA Admin reviews local evidence, approves standard cases, and escalates high-risk cases to State Coordinator. |
| Field scalability      | A single LGA-scoped agent would struggle to cover artisan density, support visits, and dispute follow-up.                   | Each LGA can have multiple Area Agents, giving the platform local coverage without forcing the state team into day-to-day work.                     |

# 1\. Executive Summary and Business Vision

Lartisan is a premium localized service marketplace that connects customers with verified local artisans, including plumbers, electricians, carpenters, mechanics, tailors, technicians, decorators, and other skilled service providers. The platform combines service discovery, booking, verification, payment, subscription, and field-support workflows into a single trusted operating system for local services.

## 1.1 Problem Statement

- Customers rely heavily on informal referrals and often cannot verify quality, pricing, reliability, safety, or service history before inviting an artisan into their home or business.
- Artisans have limited digital visibility, inconsistent access to customers, weak business tooling, and few practical channels for building trust beyond word-of-mouth.
- Local service delivery requires physical field operations, because verification, onboarding, dispute handling, and training often happen in shops, markets, estates, communities, and artisan clusters.
- State-level administration alone is too broad for reliable day-to-day supervision. A Local Government Area can contain many wards, communities, and service clusters, so LGA-level administration is required before area-level field work.

## 1.2 Solution Statement

Lartisan provides a trusted digital and field-enabled ecosystem. Customers can discover, compare, book, track, pay, and review verified artisans. Artisans can publish professional service profiles, manage requests, receive payments, maintain availability, and subscribe to platform packages. Operations teams can run localized onboarding, KYC validation, support, dispute resolution, and performance reporting through a structured admin hierarchy.

**Core operating principle**

The platform must be digital-first for customers and artisans, but field-aware for operations. Lartisan succeeds only if the application reflects how local governments, wards, markets, and artisan clusters actually operate.

## 1.3 Target Outcomes

- Increase customer confidence through verified artisan profiles, transparent status tracking, controlled communication, and verified reviews.
- Increase artisan earnings by creating a dependable digital demand channel and practical business tools for booking, catalog, subscription, wallet, and payout management.
- Support offline and semi-digital artisans through agent-assisted registration, physical verification, and local training.
- Scale operations without central bottlenecks by delegating state, LGA, and area responsibilities to the right level of authority.

## 1.4 Target Implementation Context

The BRS remains implementation-aware but business-led. The expected product direction is a Laravel monolith supported by InertiaJS, Vue 3, TailwindCSS, FilamentPHP for administration, Spatie roles and permissions, Spatie Media Library for KYC and service media, SMS/WhatsApp notifications, and Paystack or Flutterwave for payments and payouts.

# 2\. Stakeholders and User Roles

The platform supports customers, artisans, and a multi-tier administration structure. The revised hierarchy distinguishes policy ownership, state oversight, LGA operations, and hyper-local field execution.

## 2.1 Role Hierarchy at a Glance

| **Level** | **Role**                   | **Primary scope**                           | **Primary responsibility**                                                                                                   |
| --------- | -------------------------- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| 1         | Super Admin                | National / platform-wide                    | Own global configuration, policy, finance oversight, admin creation, and system-wide escalation.                             |
| 2         | State Coordinator          | State                                       | Oversee state growth, LGA Admins, state KPIs, escalated verification, major disputes, and state-level operations.            |
| 3         | Local Government Admin     | One Local Government Area                   | Manage all area agents, local verification queues, LGA dashboards, support escalation, training, and local growth execution. |
| 4         | Area Agent / Field Manager | Ward, community, market, estate, or cluster | Register artisans, conduct physical visits, support onboarding, capture KYC evidence, and handle first-line field issues.    |
| 5         | Artisan                    | Owned service profile and bookings          | Publish services, manage availability, execute bookings, communicate with customers, and receive payouts.                    |
| 6         | Customer                   | Own bookings and profile                    | Discover artisans, request services, track work, pay securely, and review completed jobs.                                    |

## 2.2 Customer

Customers may use the platform as guests or registered users.

- Guest Customer: browses services, views artisan profiles, verifies a phone number with OTP, enters a service address, and submits a booking without creating a permanent password.
- Registered Customer: saves profile details, manages multiple addresses, accesses booking history, favorites artisans, and uses in-app chat during eligible booking states.
- Customer responsibilities: provide accurate address information, confirm job completion, make payment through the platform, and leave verified reviews only for completed paid bookings.

## 2.3 Artisan

- Creates or receives an assisted profile containing identity details, service categories, shop or service location, portfolio images, starting prices, and payout information.
- Uploads KYC documents such as government ID, self-portrait, physical address evidence, and optional business registration documents.
- Must pass verification and maintain an active subscription before listings become public.
- Manages booking requests, work status, customer communication, availability, subscription renewal, wallet balance, and payout details.

## 2.4 Area Agent / Field Manager

Area Agents are no longer responsible for an entire LGA by default. They operate inside a smaller physical territory such as a ward, community, market, estate, trade cluster, or operational zone assigned by the LGA Admin.

- Performs direct field marketing to artisans in shops, markets, workshops, and local clusters.
- Registers offline or non-technical artisans using the agent panel or agent app.
- Captures physical evidence, photos, shop coordinates, KYC documents, and visit notes.
- Provides first-line support, app guidance, subscription reminders, and local follow-up.
- Flags suspicious profiles, failed visits, location mismatch, unsafe behavior, or disputes to the LGA Admin.

## 2.5 Local Government Admin

The Local Government Admin is the new operating tier. This role is responsible for day-to-day LGA-level execution and bridges the gap between state strategy and field agents.

- Creates and supervises Area Agents within the assigned LGA, subject to State Coordinator policy and approval rules.
- Monitors artisan onboarding, KYC queues, agent visit completion, local disputes, subscription activation, and local customer growth.
- Approves standard-risk artisan profiles after Area Agent checks are complete, or escalates exceptions to the State Coordinator.
- Assigns territories to Area Agents and rebalances coverage across wards, communities, markets, and clusters.
- Handles LGA-level support escalations, local training, agent performance review, and operational reporting.

## 2.6 State Coordinator

- Manages all Local Government Admins and state-wide platform operations.
- Approves or deactivates LGA Admins according to Super Admin policy.
- Reviews escalated KYC, disputed verification, high-value disputes, regional fraud patterns, and state-level performance metrics.
- Coordinates statewide campaigns, local partnerships, reporting, and operational standards.

## 2.7 Super Admin

- Owns global settings, subscription packages, commission rates, role permissions, payout policy, service categories, security policy, and platform-level reporting.
- Creates and governs State Coordinator accounts and platform-wide admin access.
- Monitors national revenue, transaction volume, system health, compliance, dispute trends, payout schedules, and risk controls.

## 2.8 Role Permission Matrix

| **Capability**             | **Primary access**                | **Business rule**                                                                                                                                     |
| -------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Global settings            | Super Admin                       | State, LGA, and Area roles may view only the configuration required to operate their scope.                                                           |
| State Coordinator accounts | Super Admin                       | Only platform-level authority can create, suspend, or remove State Coordinators.                                                                      |
| LGA Admin accounts         | Super Admin and State Coordinator | State Coordinator may recommend, manage, or audit LGA Admins according to platform policy.                                                            |
| Area Agent accounts        | LGA Admin, with State oversight   | LGA Admin creates and assigns Area Agents; State Coordinator can approve, audit, or deactivate by policy.                                             |
| Artisan registration       | Artisan and Area Agent            | Artisans may self-register; Area Agents may register offline artisans on their behalf.                                                                |
| Standard KYC approval      | LGA Admin                         | Area Agent recommends after field check. State Coordinator handles high-risk, duplicate, or disputed cases.                                           |
| Local dispute handling     | LGA Admin and Area Agent          | Area Agent handles first-line evidence collection; LGA Admin decides local cases unless escalation is required.                                       |
| Service catalog moderation | Artisan, with admin moderation    | Artisan owns the catalog. Admins may moderate, suspend, or request corrections within their scope.                                                    |
| Booking creation           | Customer                          | Area Agents may assist operationally but should not create customer bookings as themselves.                                                           |
| Financial reporting        | Scoped by role                    | Super Admin sees global reports, State sees state reports, LGA sees LGA reports, Area Agent sees assigned territory reports, artisans see own wallet. |

# 3\. Core Business Workflows and Journeys

## 3.1 Artisan Onboarding and Activation

Lartisan must support both self-registration and field-assisted registration. Verification happens before subscription payment so artisans are not charged before the platform has confirmed eligibility.

### Path A: Artisan Self-Registration

- Artisan signs up using phone number and OTP, then secures the account with a password or approved authentication method.
- Artisan enters identity, service category, shop or service address, operating area, pricing guidance, availability preferences, and portfolio information.
- Artisan uploads government ID, self-portrait, address evidence, optional business registration, and service media.
- System maps the artisan to State, LGA, and Area using address and geographic data, then places the profile in the correct verification queue.

### Path B: Agent-Assisted Registration

- Area Agent visits a market, workshop, estate, ward, community, or artisan cluster and introduces the platform.
- Area Agent records artisan details, shop coordinates, service categories, photos, KYC files, and local references if needed.
- System marks the profile as submitted by agent, associates it with the Area Agent and LGA Admin, and sends the artisan an OTP or onboarding link.
- Artisan can later claim the account, set a password, complete missing details, and continue subscription activation after approval.

### Unified Verification and Activation

| **Step**                 | **Owner**         | **Requirement**                                                                                              |
| ------------------------ | ----------------- | ------------------------------------------------------------------------------------------------------------ |
| 1\. Intake               | System            | Route profile to the correct State, LGA, and Area based on location and service data.                        |
| 2\. Field check          | Area Agent        | Visit or validate the artisan location, capture notes, confirm business existence, and flag inconsistencies. |
| 3\. LGA review           | LGA Admin         | Review documents, agent evidence, local risk signals, duplicate records, and approve standard-risk profiles. |
| 4\. Escalation           | State Coordinator | Handle high-risk KYC, identity conflicts, agent disputes, repeated complaints, or policy exceptions.         |
| 5\. Activation notice    | System            | Notify approved artisans through SMS, WhatsApp, email, or in-app notification.                               |
| 6\. Subscription paywall | Artisan           | Select Basic, Pro, or Premium subscription and pay through Paystack or Flutterwave.                          |
| 7\. Go live              | System            | Publish the artisan listing after successful payment and active verification status.                         |

## 3.2 Customer Booking Flow

- Customer selects a service category, preferred schedule, location, and optional job description or photos.
- Guest customers provide name, phone number, OTP verification, and service address. Registered customers can choose saved addresses and saved preferences.
- System recommends suitable artisans using location, availability, category match, rating, subscription visibility rules, and operational status.
- Artisan receives booking notification and accepts or rejects within a configurable time window.
- After acceptance, booking moves through accepted, in progress, finished, customer confirmed, paid, settled, and reviewed states.
- Payment is processed through approved payment providers. Commission is deducted and the net amount is added to the artisan wallet pending payout policy.

## 3.3 Communication Flow

- Registered customers and artisans may use in-app chat only while there is a pending, accepted, or active booking.
- Guests receive transactional notifications through SMS, WhatsApp, email, and secure deep-links instead of persistent chat.
- All booking status changes must trigger clear notifications to relevant parties.
- Sensitive contact details should be protected according to platform privacy policy and anti-circumvention rules.

## 3.4 Review, Complaint, and Dispute Flow

| **Trigger**                                | **First owner**     | **Escalation path**                | **Expected outcome**                                                                 |
| ------------------------------------------ | ------------------- | ---------------------------------- | ------------------------------------------------------------------------------------ |
| Customer rates completed paid job          | System              | LGA Admin if flagged               | Verified review is published unless policy violation is detected.                    |
| Artisan disputes review                    | Area Agent          | LGA Admin, then State Coordinator  | Evidence is reviewed and review is retained, hidden, edited by policy, or removed.   |
| Customer reports misconduct or failed work | LGA Admin           | State Coordinator for severe cases | Case record is created with evidence, status, decision, and possible account action. |
| Payment or payout complaint                | Support / LGA Admin | Finance / Super Admin              | Transaction history is checked and settlement correction is issued if required.      |

## 3.5 Payout and Subscription Flow

- Artisan subscription status controls listing visibility, lead access, promotional placement, and package-specific features.
- Customer payments are recorded against bookings and settlement records. Commission and fees must be transparent in transaction logs.
- Artisan wallet balance is updated after booking completion according to escrow and confirmation rules.
- Scheduled payouts run weekly or monthly to verified bank accounts through approved payment providers.
- Failed payouts must create retry, support, and finance review tasks.

# 4\. Functional Requirements

## 4.1 Authentication and Identity

- Support OTP sign-up, login, password recovery, and account claiming for customers, artisans, and operational users.
- Support role-based access using explicit permissions for Super Admin, State Coordinator, LGA Admin, Area Agent, Artisan, Registered Customer, and Guest Customer.
- Allow Super Admin to manage permission templates while preventing lower roles from self-elevating privileges.
- Maintain audit logs for authentication, role changes, verification decisions, payout changes, and admin account actions.

## 4.2 Location and Operating Territory Management

- Maintain structured geographic records for country, state, local government area, ward or area, and optional market or cluster.
- Assign each Area Agent to one or more defined operating territories inside an LGA.
- Map artisans and bookings to the correct State, LGA, and Area using service address, shop location, and geolocation where available.
- Allow LGA Admins and State Coordinators to reassign territories with traceable reason codes.

## 4.3 Artisan Profile and Service Catalog

- Allow artisans to create and update service categories, service descriptions, starting prices, availability status, portfolio images, and operating coverage.
- Support Online, Busy, and Offline availability states with booking and discovery implications.
- Allow profile and catalog moderation by authorized operational users.
- Use media handling for KYC, portfolio, proof-of-work images, and dispute evidence.

## 4.4 KYC and Verification

- Collect government ID, self-portrait, contact details, physical address, shop location, optional CAC business registration, and field visit evidence.
- Support approval, rejection, return-for-correction, suspension, and escalation statuses.
- Enable Area Agents to submit visit notes, photos, coordinates, and field checklist results.
- Enable LGA Admins to approve standard-risk cases and State Coordinators to handle high-risk or disputed cases.

## 4.5 Search, Discovery, and Booking

- Allow customers to browse categories, search by keyword, sort by location, rating, price, availability, and relevance.
- Prioritize verified and active artisans within the customer's LGA, nearby Area, or declared service radius.
- Support guest booking with OTP verification and registered booking with saved addresses.
- Track booking lifecycle states and notify all relevant parties at each state transition.

## 4.6 Payments, Wallet, Commission, and Payout

- Support customer payments through approved payment channels, including card, transfer, USSD, and other local methods offered by provider integrations.
- Calculate platform commission, payment fees, refunds, adjustments, and net artisan earning for each booking.
- Maintain wallet ledger entries that are immutable after posting, with correction entries instead of destructive edits.
- Support automated and manual payout review flows for verified bank accounts and failed payout recovery.

## 4.7 Subscriptions and Package Management

- Support Basic, Pro, and Premium packages or equivalent configurable plans.
- Control visibility, promotional features, lead volume, analytics access, and package-specific benefits by active subscription status.
- Send renewal reminders through in-app, SMS, WhatsApp, or email channels.
- Allow Super Admin to configure plan price, duration, grace period, and renewal rules.

## 4.8 Communication and Notifications

- Provide controlled in-app chat for registered customers and artisans during eligible booking states.
- Send transactional SMS, WhatsApp, email, and push notifications for OTP, booking, acceptance, work start, completion, payment, review, subscription, and payout events.
- Provide secure guest tracking links that do not require a permanent password.
- Log notification delivery status where provider integrations support it.

## 4.9 Reviews, Trust, and Moderation

- Allow only verified paid bookings to generate customer reviews.
- Support 1-5 star ratings, comments, proof-of-work photos, and response or dispute workflows.
- Detect suspicious review patterns and route flagged items to the appropriate Area Agent, LGA Admin, or State Coordinator.
- Expose trust indicators such as verified status, completed jobs, ratings, response rate, and subscription status where appropriate.

## 4.10 Admin Dashboards

- Super Admin dashboard must show national metrics, configuration, admin management, finance oversight, subscription reporting, payout monitoring, dispute trends, and system health.
- State Coordinator dashboard must show state-wide growth, LGA Admin performance, escalated KYC, state disputes, revenue, active artisans, and customer activity.
- LGA Admin dashboard must show LGA onboarding pipeline, area-agent performance, local verification queues, local disputes, subscription activation, field visit completion, and support status.
- Area Agent dashboard must show assigned territory, pending visits, assisted registrations, local artisan list, visit checklist, support tasks, and first-line dispute flags.

## 4.11 Reporting Requirements by Role

| **Role**          | **Required reports**                                                                                                                                   |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Super Admin       | National revenue, transaction volume, commission, subscription revenue, payout queue, disputes, KYC risk, user growth, system health.                  |
| State Coordinator | State revenue, LGA performance, LGA Admin activity, escalated KYC, state campaigns, customer growth, artisan growth, dispute aging.                    |
| LGA Admin         | Area Agent activity, artisan onboarding funnel, visit completion, local disputes, subscription conversion, customer demand by category, support tasks. |
| Area Agent        | Assigned artisans, pending visits, completed registrations, failed verifications, support notes, local campaign activity, agent-assisted conversion.   |
| Artisan           | Bookings, earnings, subscription status, reviews, payout history, service performance, customer repeat activity.                                       |

# 5\. Non-Functional Requirements

| **Category**           | **Requirement**                                                                                                                                                      |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Performance            | Standard catalog discovery and profile APIs should respond within 300ms under normal peak traffic targets, excluding third-party provider latency.                   |
| Scalability            | Architecture must support growth from a pilot LGA to multiple states without redesigning the role hierarchy or territory model.                                      |
| Security               | KYC files, identity data, bank details, chat records, and payment records must be protected with role-based access, encryption where appropriate, and audit logging. |
| Privacy                | Platform must comply with applicable local privacy expectations, including NDPR-style consent, purpose limitation, data minimization, and secure retention.          |
| Availability           | Customer discovery, booking, artisan availability, and payment initiation should be prioritized as high-availability paths.                                          |
| Auditability           | Admin actions, verification decisions, payout changes, role assignments, and dispute outcomes must be traceable with actor, timestamp, and reason where applicable.  |
| Mobile-first usability | Customer, artisan, and field-agent journeys must work cleanly on mobile devices with low-friction forms and resilient file upload flows.                             |
| Localization           | Support Nigerian Naira, local banks, BVN or bank-account verification where available, local phone numbers, states, LGAs, wards, and local payment channels.         |
| Observability          | System should expose operational logs, payment provider failure tracking, notification provider status, queue health, and error monitoring.                          |
| Data recovery          | Backups, restore testing, and retention policies must cover user, booking, KYC, payment, wallet, admin, and audit records.                                           |

# 6\. Key Business Rules

## 6.1 Territory Rules

- Every artisan must belong to a State and LGA before becoming publicly listed.
- Every Area Agent must be assigned to an LGA and at least one operating area, ward, market, community, estate, or cluster.
- A Local Government Admin may manage multiple Area Agents but only within the assigned LGA unless the State Coordinator grants temporary cross-LGA access.
- A State Coordinator can view and manage operations across all LGAs in the assigned state.

## 6.2 Verification Rules

- Artisan profiles cannot go live until KYC is approved and a subscription is active.
- Area Agents can recommend approval but cannot independently publish an artisan unless a policy override is granted.
- LGA Admins can approve standard-risk profiles after complete field evidence is available.
- State Coordinators must review escalated, high-risk, duplicate, disputed, or policy-exception profiles.
- Rejected or suspended profiles must keep a reason code and notification record.

## 6.3 Booking and Payment Rules

- Only available verified artisans may receive new booking requests, subject to subscription status and service category match.
- Customers can review only completed and paid bookings.
- Platform commission must be calculated from the booking payment according to active platform settings at the time of transaction.
- Wallet corrections must be posted as adjustment entries rather than overwriting original ledger records.
- Refund, cancellation, and dispute outcomes must be reflected in booking, wallet, and reporting records.

## 6.4 Subscription Rules

- Subscription package benefits must be configurable by Super Admin.
- Expired subscriptions may reduce listing visibility, prevent new lead access, or unpublish the artisan according to configured grace-period rules.
- Subscription payment and renewal events must be tied to artisan account history and reporting.

# 7\. MVP Acceptance Criteria

The MVP is acceptable when the platform can demonstrate the complete trust loop: verified artisan onboarding, customer booking, secure payment, completion confirmation, review, wallet settlement, and local admin oversight.

| **Area**           | **Acceptance criteria**                                                                                           |
| ------------------ | ----------------------------------------------------------------------------------------------------------------- |
| Admin hierarchy    | Super Admin, State Coordinator, LGA Admin, and Area Agent roles exist with scoped dashboards and permissions.     |
| Territory model    | System can assign State, LGA, and Area to artisans, bookings, admins, and reports.                                |
| Artisan onboarding | Self-registration and agent-assisted registration both route to Area Agent field checks and LGA Admin review.     |
| Verification       | Standard KYC can be approved by LGA Admin, while risky or disputed KYC can be escalated to State Coordinator.     |
| Customer booking   | Guest and registered customers can submit bookings, track status, receive notifications, and complete payment.    |
| Payments           | Payment, commission deduction, artisan wallet entry, and payout queue are recorded with auditable ledger history. |
| Trust              | Only completed paid bookings can produce reviews, and disputed reviews can be routed through operations.          |
| Reporting          | Each admin level sees metrics limited to the appropriate operational scope.                                       |

# 8\. Assumptions and Open Decisions

## 8.1 Assumptions

- The initial market is Nigeria or a similar local-government operating environment where State, LGA, ward, and community structures are meaningful.
- Local Government Area boundaries are large enough to justify a dedicated LGA Admin before assigning smaller Area Agents.
- Area can mean ward, community, market, estate, trade cluster, or other operational zone depending on the launch geography.
- Some artisans will remain offline or semi-digital, so agent-assisted registration is a core feature rather than an exception.
- The BRS defines business requirements and operating rules; detailed UI, database, API, and sprint planning should follow in separate artifacts.

## 8.2 Open Decisions

| **Decision**             | **Recommended direction**                                                                                                                                     |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Exact definition of Area | Start with ward/community/market/cluster as configurable territory types under each LGA.                                                                      |
| LGA Admin approval limit | Allow standard-risk approval at LGA level; require State Coordinator for high-risk, duplicate, policy exception, or disputed cases.                           |
| Agent compensation       | Define whether Area Agents receive fixed pay, onboarding commission, subscription commission, performance bonus, or hybrid compensation.                      |
| Escrow timing            | Confirm whether payment is collected before work starts or after customer confirms completion. The product should support the chosen risk model consistently. |
| Subscription packages    | Finalize Basic, Pro, and Premium benefits before implementation so discovery and lead-access rules are clear.                                                 |

# Appendix A: Revised Admin Model

The original document treated the Area Agent as LGA-scoped. The revised model introduces Local Government Admin because an LGA can contain multiple wards, communities, markets, and artisan clusters. This makes LGA Admin the operational manager, while Area Agent becomes the field execution role.

| **Hierarchy**          | **Operational meaning**                                                                               |
| ---------------------- | ----------------------------------------------------------------------------------------------------- |
| Super Admin            | Owns national policy, settings, platform finance, and high-level governance.                          |
| State Coordinator      | Owns state performance and supervises LGA Admins.                                                     |
| Local Government Admin | Owns one LGA and supervises local field operations, verification queues, disputes, and area coverage. |
| Area Agent             | Works in a specific ward, community, market, estate, trade cluster, or operational area.              |
| Artisan                | Provides services and manages bookings through verified subscription-backed profile.                  |
| Customer               | Books, pays, confirms, and reviews services.                                                          |

# Appendix B: Suggested Status Values

| **Object**   | **Suggested statuses**                                                                                                       |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------- |
| Artisan KYC  | Draft, Submitted, Field Check Pending, Field Check Complete, LGA Review, Approved, Returned, Rejected, Escalated, Suspended. |
| Subscription | Trial, Active, Grace Period, Expired, Cancelled, Suspended.                                                                  |
| Booking      | Requested, Accepted, Rejected, Cancelled, In Progress, Finished, Customer Confirmed, Paid, Disputed, Settled, Reviewed.      |
| Payout       | Pending, In Review, Processing, Paid, Failed, Retrying, Cancelled, Adjusted.                                                 |
| Dispute      | Open, Awaiting Evidence, Under LGA Review, Escalated to State, Resolved, Reopened, Closed.                                   |