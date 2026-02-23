# Sentinel - Review State Machine

This file defines run lifecycle contracts for automated and manual reviews.

---

## States

- `queued`:
  run accepted and waiting for worker execution
- `in_progress`:
  active review execution in worker context
- `completed`:
  review finished successfully and outputs persisted
- `failed`:
  review execution failed irrecoverably
- `skipped`:
  review intentionally not executed (policy/plan/config reasons)

Terminal states: `completed`, `failed`, `skipped`.

---

## Allowed Transitions

- `queued -> in_progress`
- `in_progress -> completed`
- `in_progress -> failed`
- `queued -> skipped`
- `in_progress -> skipped` (only for deterministic policy/eligibility outcomes)
- `queued -> skipped[superseded]` (newer push to same PR)
- `in_progress -> skipped[superseded]` (newer push to same PR)

No transition is allowed out of terminal states.

---

## Entry Paths

- Pull request webhook (auto-review)
- Manual review command
- Internal re-dispatch/retry when explicitly allowed

All entry paths must create or target a run record before worker execution.

---

## Skip Contracts

Common skip reasons include:

- installation inactive
- auto-review disabled
- trigger/policy mismatch
- plan limit reached
- provider keys unavailable
- superseded (newer push to same PR)

Skip reasons must be persisted and observable.

---

## Side Effects by Phase

- `queued`:
  queue dispatch metadata recorded
- `in_progress`:
  context, provider, and execution telemetry recorded
- `completed`:
  findings, annotations, and summary published
- `failed`/`skipped`:
  failure/skip activity and user-facing status messaging published

---

## Reliability Rules

- Execution must be idempotent across retries.
- Duplicate webhook deliveries must not create duplicate terminal side effects.
- Failure handling must always resolve run into a deterministic terminal state.

