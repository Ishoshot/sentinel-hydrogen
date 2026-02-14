# Sentinel - Queues and Jobs

This file defines background execution contracts.

---

## Queue Principles

- Jobs are idempotent and retry-safe.
- Queue routing is explicit and priority-driven.
- Higher-tier customer workloads receive higher-priority review lanes.

---

## Queue Topology

Core queue groups:

- `system`
- `webhooks`
- `reviews-enterprise`
- `reviews-paid`
- `reviews-default`
- `briefings-default`
- `commands`
- `annotations`
- `notifications`
- `sync`
- `default`
- `code-indexing`
- `long-running`
- `bulk`

Queue purpose must stay explicit in code and docs.

---

## Routing Rules

- Webhook ingress jobs route to `webhooks`.
- Review execution routes by workspace plan tier.
- Annotation/comment publication uses annotation or notification lanes.
- Heavy indexing and backfills use `code-indexing`, `long-running`, or `bulk`.

---

## Job Design Rules

Each job should:

- own one execution concern
- validate prerequisites early
- avoid hidden side effects
- emit structured telemetry

---

## Retry and Failure Rules

- Retries must not duplicate external side effects.
- Exhausted retries must emit actionable logs and failure events.
- Poison-message behavior must be explicit (fail fast vs defer).

---

## Operational Rules

- Horizon queue balancing should reflect business priority.
- Queue names and priorities are contract-level behavior.
- Any routing change requires test coverage and changelog visibility.

