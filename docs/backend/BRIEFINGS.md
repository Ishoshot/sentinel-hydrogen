# Sentinel - Briefings

This file defines the Briefings feature contract.

---

## Feature Goal

Generate and deliver concise, recurring workspace updates from repository and run activity.

---

## Core Concepts

- `Briefing`:
  a saved template/configuration for a recurring or on-demand summary
- `BriefingGeneration`:
  one execution instance of a briefing
- `BriefingSubscription`:
  delivery cadence and channel configuration
- `BriefingShare`:
  public or restricted share link for generated output

---

## Data Contract

Primary tables:

- `briefings`
- `briefing_generations`
- `briefing_subscriptions`
- `briefing_shares`
- `briefing_downloads`
- `slack_integrations`

All records are workspace-scoped unless explicitly public-share specific.

---

## Execution Flows

### Manual Generation

1. User triggers generation.
2. Job pipeline collects data and generates content.
3. Generation status updates are persisted.
4. Output is stored and rendered in workspace UI.

### Scheduled Generation

1. Scheduler selects due subscriptions.
2. Plan and policy checks run.
3. Generation executes.
4. Delivery publishes to selected channels.

---

## Delivery Channels

- Email
- Slack webhook/channel integration
- Public share link

Each channel must have deterministic success/failure tracking.

---

## Real-Time and UX Contract

- Generation progress updates must be observable by the UI.
- Failures must surface actionable status to users.
- Share links must honor expiration and access controls.

---

## Limits and Security

- Enforce plan limits on generation volume and channel usage.
- Do not expose cross-workspace data in generated output.
- Validate and sanitize external channel payloads.

---

## Testing Contract

Cover at minimum:

- generation success/failure transitions
- scheduling eligibility and denial behavior
- delivery channel success/failure
- share link permission and expiry behavior

