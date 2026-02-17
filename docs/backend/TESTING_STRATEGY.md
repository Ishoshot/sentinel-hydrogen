# Sentinel - Testing Strategy

This file defines backend test contracts.

---

## Testing Principles

- Protect behavior, not implementation details.
- Prioritize deterministic tests.
- Test business-critical state transitions and side effects.

---

## Test Pyramid for Sentinel

### Unit Tests

Use for:

- pure transformations
- parser/mapper/builder logic
- policy/guard decision rules

### Feature Tests

Use for:

- actions and orchestration flows
- API endpoints and authorization
- queue/job dispatch and integration boundaries

### Integration-Slice Tests

Use for:

- provider clients/adapters
- webhook handling contracts
- billing/subscription sync behavior

---

## Critical Coverage Areas

Must stay covered:

- workspace scoping and authorization
- review run state transitions
- command run execution and result publication
- queue routing by tier and job type
- briefing generation and delivery outcomes
- billing plan/limit enforcement

---

## Mocking Rules

- Mock external boundaries, not core domain behavior.
- Prefer fakes for events/notifications/queues where available.
- Keep provider payload fixtures realistic and minimal.
- Never allow real outbound network calls in tests.
- Tests must fake/mimic external APIs (including GitHub SDK paths) and fail fast on stray requests.

---

## Queue and Job Testing

- Assert dispatch destination where routing matters.
- Assert idempotent outcomes under retry conditions.
- Assert terminal state resolution for failure paths.

---

## CI Contract

Minimum merge gate:

1. formatting/lint/static checks
2. targeted test slices for touched domains
3. full suite in CI matrix

If behavior changes, tests must fail before change and pass after change.
