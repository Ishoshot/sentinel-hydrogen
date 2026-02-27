---
trigger: always_on
---

# Code Review & Quality

## Self-Review Before Completion

After writing or modifying code, **pause and critically review your own work** before considering the task done. Do not assume correctness — verify it. Specifically check for:

- **Logic bugs:** Does every method do exactly what its name and docblock promise? Trace through edge cases mentally.
- **Blast radius:** Does a method that should affect one record accidentally affect many? Does a query scope filter tightly enough?
- **Dependency correctness:** When an Action calls a Service, does the Service's behavior match what the Action expects in all cases? Look for mismatches in scope, side effects, or return values.
- **Race conditions:** If this code runs concurrently (e.g., queued jobs, webhook retries), does it still behave correctly?
- **State transitions:** Are only the intended records transitioned? Could adjacent or unrelated records be caught in the blast?
- **Workspace isolation:** Does every query and mutation scope to `workspace_id`? Could data leak across tenants?

If you find an issue during self-review, fix it immediately — do not leave it for the user to catch.

## Code Review Gate (Required)

Before considering any non-trivial code-writing task complete, run **all 4 reviewers in parallel** from the main conversation:

- `review-correctness` — logic bugs, regressions, state transitions, workspace scoping
- `review-architecture` — layering, naming conventions, queue routing, migration rules
- `review-tests` — coverage gaps, assertion quality, Pest patterns, workspace scoping in tests
- `review-security` — OWASP risks, workspace isolation, BYOK credential handling, webhook verification, concurrency/idempotency

**Review cycle:**

1. Run all 4 reviewers in parallel after completing your changes
2. Do not rely on nested subagents (a subagent cannot spawn other subagents)
3. Fix reported issues
4. Re-run only the reviewer(s) that requested changes
5. Repeat until all reviewers approve
6. If reviewer resume fails, start a new reviewer run
7. In web/remote workflows, complete this before pushing

All reviewer feedback should be practical and high-signal, not pedantic.

Work is only complete when all reviewer outputs confirm the changes are acceptable.
