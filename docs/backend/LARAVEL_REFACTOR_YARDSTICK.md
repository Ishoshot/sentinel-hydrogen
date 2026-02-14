# Sentinel – Laravel Refactor Yardstick

This document captures the architecture principles we will use to progressively refactor Sentinel backend code to a first-party Laravel standard.

It is intentionally pragmatic: simple, reusable, testable, and easy to change.

---

## Why This Exists

We want code that feels like Laravel core quality:

- clear flow and naming
- small, composable units
- extension points where needed
- minimal accidental complexity

This is a hard guide for backend refactors.

---

## What We Learned From Laravel AI SDK Structure

The Laravel AI SDK is a good model for design quality because it combines flexibility with simplicity.

Core patterns to emulate:

- Contract-first capabilities. Providers implement focused contracts (`TextProvider`, `ImageProvider`, etc.), not one giant interface.
- Manager + drivers. A central manager resolves configured drivers and keeps provider swapping straightforward.
- Fluent pending operations. Stateful operations are represented by dedicated pending objects instead of bloated services.
- Small base abstractions. Shared behavior lives in lightweight base classes or traits, while domain specifics stay in concrete classes.
- Pipeline and middleware composition. Cross-cutting concerns are layered through middleware/pipelines instead of duplicated inline logic.
- Evented lifecycle. Important boundaries dispatch events so behavior is observable and extensible.
- First-class fakes. Test seams are built in, so swapping real integrations for fakes is native and easy.
- Minimal service providers. Container wiring is explicit and small, with most complexity kept out of bootstrapping.

---

## Refactor Principles (Must Follow)

1. Prefer explicit orchestration over hidden magic.
2. Keep controllers thin; orchestration belongs in Actions.
3. Keep services focused; one responsibility per service.
4. Introduce interfaces only at real boundaries (external integrations, interchangeable strategies, high-volatility areas).
5. Use manager/driver patterns only when there are multiple implementations now or clearly imminent.
6. Model multi-step operations with dedicated classes (for example, Action + pipeline), not giant methods.
7. Use config-driven resolution for integrations and providers.
8. Make extension explicit with events, listeners, and middleware hooks.
9. Build testability in by design with fakes and container-bound contracts.
10. Optimize for readability first; cleverness is a regression unless it removes real complexity.

---

## Sentinel-Specific Application Rules

- Preserve `workspace_id` scoping everywhere.
- Keep current terminology from `docs/product/GLOSSARY.md`.
- Keep jobs idempotent and retry-safe.
- Use Actions for use-case orchestration and Services for focused domain logic.
- Keep model relationships and query scopes expressive; avoid ad hoc query duplication.

---

## Pattern Selection Guide

Use this when deciding how to implement/refactor.

### Action

Use when a workflow coordinates validation outcomes, authorization outcomes, transactions, services, jobs, and events.

Do not use when logic is a single pure transformation with no orchestration.

### Service

Use when logic is focused and reusable inside one domain concern.

Do not use as a dump for unrelated helper methods.

### Contract + Implementation

Use for integration boundaries, pluggable behaviors, and code that needs fake/testing swaps.

Do not introduce for one-off concrete classes without an expected alternate implementation.

### Manager + Driver

Use for runtime-selected providers (for example: AI provider, SCM provider, notification channel provider).

Do not use when selection never changes and there is only one stable implementation.

### Pipeline / Middleware

Use when cross-cutting steps are repeated across flows (policy checks, normalization, telemetry, enrichment).

Do not use when a simple private method call sequence is clearer.

---

## PR Yardstick Checklist

Every backend refactor PR should pass this checklist:

1. Does this reduce cognitive load for the next maintainer?
2. Are class responsibilities narrower than before?
3. Is orchestration separated from execution details?
4. Are boundaries represented by contracts only where useful?
5. Can integrations be swapped without touching business rules?
6. Are side effects visible via events/jobs instead of hidden inline code?
7. Is this easier to test than the previous shape?
8. Are naming, locations, and signatures consistent with adjacent Laravel code?
9. Did we avoid introducing abstraction that has no immediate payoff?
10. Did we preserve behavior and data contracts?

If 2 or more answers are "no", the refactor needs another pass.

---

## Anti-Patterns To Remove

- Fat controllers with branching business logic.
- God services that mix orchestration and low-level integration calls.
- Static helper classes replacing dependency injection.
- Premature interface sprawl with one implementation and no boundary value.
- Feature logic hidden inside jobs/listeners where the primary flow becomes opaque.
- Duplicate query/business rules spread across controllers, jobs, and listeners.

---

## Refactor Sequence (Recommended)

1. Stabilize behavior with tests around high-risk flows.
2. Extract orchestration into Actions.
3. Isolate integration boundaries behind contracts.
4. Introduce manager/driver resolution where multiple providers exist.
5. Extract repeated cross-cutting flow into middleware/pipelines.
6. Add events/fakes at seams for observability and testability.
7. Remove dead abstractions and simplify names.

---

## Definition Of Done For A Refactor

A refactor is complete when:

- behavior is unchanged (or intentionally changed and documented)
- tests prove parity and pass
- complexity and file responsibility both decrease
- extension points are explicit and minimal
- code reads like idiomatic Laravel, not a custom framework inside Laravel

