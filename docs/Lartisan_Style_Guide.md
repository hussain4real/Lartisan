# Lartisan Style Guide

Version: 1.0
Date: 29 May 2026
Palette source: Leadprenuer homepage brand variables

## 1. Brand Position

Lartisan should feel trustworthy, local, premium, and operationally competent. The product serves customers, artisans, field agents, LGA admins, state coordinators, and finance/support operators, so the interface must balance marketplace warmth with dashboard clarity.

The design language should communicate:

- Verified local skill.
- Clear accountability.
- Practical field operations.
- Financial trust.
- Calm, fast decision-making.

## 2. Color System

The base palette is derived from Leadprenuer colors found on `leadprenuer.com.ng`, then mapped into semantic Tailwind tokens in `resources/css/app.css`. Vue templates should use semantic classes such as `bg-primary`, `text-muted-foreground`, `bg-warning/10`, and `text-destructive` instead of raw brand hex utilities.

| Role                | Token                         | Light mode             | Dark mode             | Usage                                                        |
| ------------------- | ----------------------------- | ---------------------- | --------------------- | ------------------------------------------------------------ |
| Brand surface       | `brand`                       | `hsl(225 100% 22.4%)`  | `hsl(225 72% 18%)`    | Logo blocks, large brand bands, trust-heavy hero surfaces.   |
| Primary action      | `primary`                     | `hsl(225 100% 22.4%)`  | `hsl(225 100% 66%)`   | Main commitments, links, focus rings, active navigation.     |
| Secondary / warning | `secondary`, `warning`        | `hsl(38 92.1% 50.2%)` | `hsl(38 92.1% 62%)`  | Attention, review, pending, retry, and secondary actions.    |
| Success             | `success`                     | `hsl(160 84% 31%)`     | `hsl(152 76% 48%)`    | Confirmed saves, copied codes, verified completions.         |
| Destructive         | `destructive`                 | `hsl(0 84.2% 60.2%)`   | `hsl(0 84% 60%)`      | Failed payments, rejected KYC, deletion, suspension.         |
| App surface         | `background`                  | `hsl(210 24% 98%)`     | `hsl(225 48% 7%)`     | Default page background across public and app shells.        |
| Card surface        | `card`                        | `hsl(0 0% 100%)`       | `hsl(225 42% 10%)`    | Cards, panels, forms, popovers, and repeated records.        |
| Text                | `foreground`                  | `hsl(0 0% 11.4%)`      | `hsl(210 24% 96%)`    | Primary body copy, headings, dense operational values.       |
| Muted text          | `muted-foreground`            | `hsl(210 7.3% 37%)`    | `hsl(214 20% 70%)`    | Descriptions, metadata, helper copy, secondary labels.       |
| Border              | `border`, `input`, `sidebar-*` | `hsl(214 28% 88%)`     | `hsl(225 24% 20%)`    | Dividers, cards, tables, input boundaries, sidebar chrome.   |

### Usage Rules

- Light mode can use deep brand blue for primary CTAs, logo blocks, navigation anchors, and brand panels.
- Dark mode should use the lighter `primary` token for links, actions, rings, and active states. Use `brand` in dark mode for larger surfaces only, not dense text or small icons.
- Use orange/warning for secondary actions and states that need attention: assign agent, review payout, pending KYC, retry upload.
- Use success only for confirmation states, not decorative emphasis.
- Use destructive only for failed, rejected, destructive, suspended, or high-risk states.
- Treat surface, card, text, muted text, and border as system roles. Do not replace them with one-off gray or neutral utilities.
- Avoid pages dominated by blue. Pair brand and primary with card surfaces, muted text, warning, success, and restrained destructive states.
- Raw brand hex values are acceptable inside this style guide's documentation swatches, but production Vue templates should use semantic theme tokens.

## 3. Typography

Use Instrument Sans as the application font. It is already configured in the app and works well for both product pages and operational dashboards.

| Style           | Usage                                                        |
| --------------- | ------------------------------------------------------------ |
| Page title      | Main screen identity, one per page.                          |
| Section heading | Form sections, dashboard groups, admin modules.              |
| Body text       | Descriptions, instructions, support copy.                    |
| Label           | Inputs, filters, table metadata.                             |
| Code/ID         | References, transaction IDs, payout references, webhook IDs. |

Guidelines:

- Keep headings plain and descriptive.
- Prefer short labels over clever copy.
- Use sentence case for UI text.
- Use active verbs in actions: Approve, Assign, Review, Suspend, Retry.
- Do not use hero-size type inside dashboard panels or settings forms.

## 4. Layout Principles

### Customer Surfaces

Customer discovery may be more visual and inviting. It should still show trust signals early:

- Verified artisans.
- Location match.
- Rating and completed jobs.
- Starting price.
- Availability.
- Clear next action.

### Operations Surfaces

Admin, LGA, state, and agent screens should be dense but organized:

- Tables for queues and finance records.
- Filters for state, LGA, area, status, owner, and date.
- Cards only for summary metrics or repeated records.
- No nested cards.
- Keep page sections unframed unless the user is acting inside a specific tool or list item.

### Mobile

Field-agent workflows must work cleanly on mobile:

- Large touch targets.
- Single-column forms.
- Persistent action area for save/submit where useful.
- Upload progress and retry states.
- Clear offline or failed-upload messaging.

## 5. Components

### Buttons

| Button      | Usage                                                       |
| ----------- | ----------------------------------------------------------- |
| Primary     | Main action on a screen or modal.                           |
| Secondary   | Useful but lower-priority action.                           |
| Outline     | Navigation, evidence viewing, non-committal actions.        |
| Destructive | Suspend, delete, reject, cancel payout, close with penalty. |

Use icons for recognizable tools and status actions when available through `lucide-vue-next`.

### Badges

Badges should represent stable state, not decorative emphasis.

| State                               | Color              |
| ----------------------------------- | ------------------ |
| Approved, verified, completed, paid | Blue or neutral.   |
| Pending, queued, in review          | Orange or neutral. |
| Escalated, needs evidence           | Orange.            |
| Rejected, failed, suspended         | Red.               |
| Draft, inactive, archived           | Gray.              |

### Tables

Operational tables should include:

- Human-readable name.
- Scope: state, LGA, area, or owner.
- Status.
- Last activity.
- Next action.
- Risk or priority where relevant.

### Forms

Forms should:

- Use clear labels and helper text only when it prevents mistakes.
- Group related fields.
- Show validation near the field.
- Preserve uploaded media state.
- Confirm destructive or irreversible decisions.

## 6. Status Language

Use consistent verbs and status names across customer, artisan, and admin views.

| Avoid      | Prefer    |
| ---------- | --------- |
| Done       | Completed |
| OK         | Approved  |
| Bad        | Rejected  |
| Waiting    | Pending   |
| Problem    | Escalated |
| Money sent | Paid      |

Status labels should match the backend enum vocabulary wherever practical.

## 7. Accessibility

- Primary blue buttons require white text.
- Orange backgrounds need ink or very dark blue text unless used as a small badge on dark surfaces.
- Do not rely on color alone for status; pair with text and icons.
- Maintain visible focus states through the existing `ring` token.
- Keep touch targets at least 40px where possible.
- Make dynamic content announcements available for critical payment, KYC, and upload state changes.

## 8. Live Style Guide

The live style guide is available inside authenticated settings at:

```text
/settings/style-guide
```

It demonstrates:

- Color roles.
- Typography voice.
- Journey cards.
- Status badges.
- Action hierarchy.

The live page should evolve as the production component set grows.
