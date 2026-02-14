# Sentinel - Laravel Refactor Yardstick

Use this as the quality bar for backend refactors.

---

## Refactor Goal

Code should feel first-party Laravel:

- obvious orchestration flow
- small focused collaborators
- explicit boundaries and contracts
- easy testing and provider swapability

---

## Required Principles

1. Preserve behavior while reducing complexity.
2. Keep controllers thin and actions explicit.
3. Keep services single-purpose.
4. Add interfaces only at true seams.
5. Prefer composition over inheritance-heavy designs.
6. Keep naming aligned with actual responsibility.

---

## Pattern Guidance

- Action: workflow orchestration
- Service: focused business capability
- Contract + Implementation: swappable boundary
- Strategy: interchangeable algorithm/provider choice
- Client/Adapter: external API boundary
- Builder/Factory: deterministic object/payload creation
- Policy/Guard: eligibility and authorization

Use patterns only when they reduce coupling and improve clarity.

---

## PR Checklist

- Are responsibilities narrower than before?
- Is orchestration separated from low-level execution?
- Are names role-accurate?
- Are workspace scoping rules preserved?
- Are retries/idempotency preserved for async paths?
- Did tests prove parity for touched behavior?

If two or more answers are no, refactor is not done.

---

## Anti-Patterns to Remove

- Fat controllers
- God services
- hidden side effects in helpers
- abstraction without seam value
- duplicated business rules across layers

---

## Definition of Done

A refactor is complete when:

- behavior is unchanged or intentionally documented
- code is simpler to read and modify
- tests pass and cover touched behavior
- extension points are explicit and minimal

