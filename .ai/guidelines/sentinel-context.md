---
trigger: always_on
---

# Project Paths

- **Backend:** `/Users/oluwatobi/Herd/dev/sentinel`
- **Frontend:** `/Users/oluwatobi/Herd/dev/frontend/sentinel`

> **Terminal title.** Update the iTerm title whenever the session topic changes using OSC sequences; use ⚡︎ for Codex. Run: `printf '\033]0;%s\007' "⚡︎ Topic" > "$CODEX_TTY"` where "Topic" is a short (2-4 word) description of the current task.

# Sentinel – Backend Context

## Documentation Contracts

The `/docs` folder contains authoritative contracts for this codebase. **Before modifying core features, read the relevant doc based on task type:**

| Task                            | Read                                   |
| ------------------------------- | -------------------------------------- |
| Models, migrations, queries     | `docs/backend/DATA_MODEL.md`           |
| Actions, services, jobs, events | `docs/backend/BACKEND_ARCHITECTURE.md` |
| Auth, tokens, permissions       | `docs/backend/AUTHENTICATION.md`       |
| Review flow, state transitions  | `docs/backend/REVIEW_STATE_MACHINE.md` |
| Queues, job dispatch            | `docs/backend/QUEUE_AND_JOBS.md`       |
| Briefings feature               | `docs/backend/BRIEFINGS.md`            |
| External APIs, GitHub, Polar    | `docs/backend/INTEGRATIONS.md`         |
| Code style, conventions         | `docs/backend/CODING_STANDARDS.md`     |
| Tests                           | `docs/backend/TESTING_STRATEGY.md`     |
| Sentinel config schema          | `docs/SENTINEL_CONFIG.md`              |
| Product scope, goals            | `docs/product/PRD.md`                  |
| Naming, terminology             | `docs/product/GLOSSARY.md`             |
| Billing, limits, plans          | `docs/product/PLANS_AND_LIMITS.md`     |
| UX behavior                     | `docs/product/UX_PRINCIPLES.md`        |

**Conflict handling:** If a change would violate a documented contract, stop and ask. Offer to update the doc if the change is intentional.

## Core Rules

- All data scoped to `workspace_id`
- Controllers delegate to Actions (no business logic in controllers)
- Actions orchestrate; Services encapsulate focused logic
- Jobs are idempotent and retry-safe
- Use exact terms from `GLOSSARY.md` (Workspace, Run, Finding, Member)

## Tooling

Laravel Pint (formatting), Pest (tests), Rector (refactoring)

## Principle

Clarity over cleverness. Correctness over speed. If uncertain, ask.
