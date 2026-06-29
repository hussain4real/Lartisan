# Artisan Verification And Activation

Status: current through Phase 16 implementation.

This process controls how an artisan moves from registration to an approved public marketplace listing.

## Owners

| Actor | Responsibility |
| --- | --- |
| Artisan | Completes profile, services, portfolio, and KYC evidence. |
| Area Agent | Performs field checks and records visit status, notes, checklist evidence, and coordinates. |
| Local Government Admin | Reviews standard-risk KYC and decides approve, return, reject, or escalate. |
| State Coordinator | Reviews high-risk, disputed, duplicate, or escalated KYC. |
| Super Admin | Owns global policy, reason codes, suspension oversight, and cross-state exceptions. |

## Verification Status Flow

| Status | Meaning |
| --- | --- |
| Draft | Artisan profile exists but is not submitted for review. |
| Submitted | Artisan submitted KYC evidence and is waiting for operations review. |
| FieldCheckPending | A field visit is scheduled or in progress. |
| FieldCheckComplete | A completed field visit is recorded. |
| LgaReview | KYC is under LGA review. |
| Approved | KYC is approved and the profile can become public after subscription activation. |
| Returned | KYC needs correction and can be resubmitted. |
| Rejected | KYC is blocked because evidence or legitimacy concerns are not acceptable. |
| Escalated | KYC requires State Coordinator or Super Admin review. |
| Suspended | A previously visible profile is blocked from public listing. |

## Process

1. The artisan or an Area Agent creates an artisan business profile. The profile starts as `draft`.
2. The artisan completes public profile details, services, portfolio media, and KYC evidence.
3. Submitting KYC moves the profile and latest KYC submission to `submitted`.
4. Area Agent records a field visit. Scheduled or in-progress visits set `field_check_pending`; completed visits set `field_check_complete`.
5. Completed field visits with coordinates publish verified marketplace coordinates to the artisan profile.
6. LGA Admin reviews documents, field evidence, geography, risk signals, duplicate records, and reason codes.
7. LGA Admin approves standard-risk KYC, returns incomplete KYC, rejects blocked KYC, or escalates high-risk KYC.
8. State Coordinator reviews escalated or high-risk KYC and approves, returns, or rejects.
9. Approval sets `approved_by`, `approved_at`, and makes the profile eligible for public listing.
10. The artisan selects and pays for a subscription. Public discovery requires approved verification, active subscription, active services, and public listing state.

## Guardrails

- Area Agents do not approve KYC directly; they provide field evidence.
- Every KYC decision requires a KYC decision reason code.
- Suspensions require a suspension reason code and remove public visibility.
- Sensitive identity and field evidence stays private.
- Verification decisions are recorded in status history and audit logs.

## Exceptions

- Incomplete but correctable evidence should be returned, not rejected.
- Identity conflict, location mismatch, duplicate business records, suspicious documents, unsafe behavior, or repeated complaints should be escalated.
- Suspended profiles stay hidden until an authorized operations decision changes the state through a supported workflow.
