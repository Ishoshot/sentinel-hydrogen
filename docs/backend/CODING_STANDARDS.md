# Sentinel - Backend Coding Standards

This file defines backend coding conventions.

---

## Core Standards

- Prefer clarity over cleverness.
- Keep classes focused and names responsibility-accurate.
- Preserve workspace scoping and deterministic behavior.

---

## Structural Rules

- Controllers: transport only.
- Actions: orchestration only.
- Services: focused domain logic.
- Jobs: async/idempotent execution units.
- Events/Listeners: decoupled side effects.

No business orchestration in controllers.

---

## Naming Rules

Use names that match behavior:

- `*Builder`: assembles data structures
- `*Factory`: creates objects/results
- `*Resolver`: resolves selection from context/config
- `*Parser` / `*Mapper`: transforms shapes
- `*Client`: external API boundary
- `*Handler`: use-case/event step handling
- `*Policy` / `*Guard`: allow/deny rules

Avoid ambiguous bucket names (`Support`, generic `Manager`) unless strictly justified.

---

## Laravel Conventions

- Use constructor injection.
- Use explicit return and parameter types.
- Use Form Requests for validation.
- Use Eloquent relationships/scopes before raw queries.
- Use config values via `config()`, never `env()` outside config files.

---

## Safety Rules

- No hidden side effects.
- No secret/token logging.
- Keep migrations backward-safe.
- Prefer additive schema changes before destructive changes.

---

## Testing Rules

- Every behavior change requires tests or test updates.
- Run the smallest relevant Pest slice before merge.
- Keep mocks at boundaries (providers, network, billing, queues).

---

## Style and Tooling

- Run `vendor/bin/pint --dirty`.
- Keep static analysis clean for touched paths.
- Keep PHPDoc focused on non-obvious contracts and array shapes.

