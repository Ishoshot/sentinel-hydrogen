# Sentinel - Backend Architecture

This file defines backend boundaries and ownership.
If implementation diverges, refactor code or update this document intentionally.

---

## Primary Goal

Keep backend behavior predictable under scale:

- strict workspace scoping
- explicit orchestration
- swappable integrations
- retry-safe asynchronous execution

---

## Layer Contract

- `Controllers`:
  transport only (auth, request validation handoff, response)
- `Actions`:
  use-case orchestration (policy, transaction boundaries, service composition)
- `Services`:
  focused domain logic and integration boundaries
- `Jobs`:
  async/idempotent execution units
- `Events/Listeners`:
  decoupled side effects and telemetry
- `Models`:
  persistence and relationships

Controllers do not contain business workflows.

---

## Domain Map

Key backend domains:

- Reviews
- Commands
- Briefings
- Billing/Subscriptions/Plans
- GitHub and external integrations
- Context and code indexing

Each domain should keep one responsibility per class and explicit boundaries between orchestration and execution.

---

## Core Runtime Flows

### Review Flow

1. Webhook or manual trigger reaches an Action.
2. Action performs eligibility and policy checks.
3. Run is queued.
4. Worker builds context and executes review engine.
5. Findings/annotations are persisted and published.
6. Run reaches terminal state (`completed`, `failed`, or `skipped`).

### Command Flow

1. Command payload is parsed and normalized.
2. Eligibility/plan checks run.
3. Command run is queued and executed.
4. Result is persisted and posted back to source channel.

### Briefing Flow

1. User or schedule triggers generation.
2. Data collection and summarization run in background jobs.
3. Delivery channels publish output (email, Slack, share link).
4. Generation status and telemetry are recorded.

---

## Reliability Rules

- All async jobs must be idempotent.
- Retries must not duplicate side effects.
- External API failures must produce actionable logs and deterministic failure states.
- Queue routing must follow plan-aware priorities.

---

## Multi-Tenancy Rules

- Application data is scoped by `workspace_id`.
- Cross-workspace access is forbidden by default.
- Authorization checks run before domain mutations.

---

## Extensibility Rules

- External providers must sit behind contracts.
- Provider selection must be config-driven.
- Cross-cutting behavior should use events, listeners, or pipeline-style composition.

---

## Architectural Red Flags

- Fat controllers
- God services mixing orchestration and execution
- Hidden side effects in helper classes
- Workspace scope checks done inconsistently

