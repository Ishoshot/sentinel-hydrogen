# Sentinel - Data Model

This file defines the core persistence contracts for Sentinel.
Use it when changing models, migrations, relationships, and query behavior.

---

## Data Model Principles

- Every tenant-owned record is scoped by `workspace_id`.
- Prefer explicit relationships over ad hoc joins.
- Mutating workflows must preserve auditability.
- Schema changes must protect existing contracts and state transitions.

---

## Core Entity Groups

### Identity and Access

- `users`
- `workspaces`
- `teams`, `team_members`
- `invitations`

### Repository and Provider Integration

- `providers`
- `connections`
- `installations`
- `repositories`
- `repository_settings`

### Reviews

- `runs`
- `findings`
- `annotations`

### Commands

- `command_runs`

### Briefings

- `briefings`
- `briefing_generations`
- `briefing_subscriptions`
- `briefing_shares`
- `briefing_downloads`
- `slack_integrations`

### Billing and Limits

- `plans`
- `subscriptions`
- `usage_records`
- `provider_keys`
- `provider_models`

### Activity and Metrics

- `activities`
- `workspace_daily_metrics`

---

## Critical Relationships

- `Workspace` owns repositories, runs, command runs, briefings, subscriptions, activities.
- `Repository` belongs to workspace and installation; has many runs and settings.
- `Run` belongs to workspace and repository; has many findings and annotations.
- `Briefing` belongs to workspace; has many generations/subscriptions/shares.

Relationship names should remain explicit and intention-revealing.

---

## Required Integrity Rules

- Enforce foreign keys for tenant-owned resources.
- Use constrained enums/states where lifecycle is finite.
- Keep nullable fields intentional; avoid optional-by-default schema design.
- Maintain unique constraints for identity and deduplication boundaries.

---

## Query Rules

- Prefer model relationships/scopes over `DB::` for domain operations.
- Eager load where needed to avoid N+1 regressions.
- Keep read models deterministic for run state, billing state, and briefing delivery status.

---

## Migration Safety Rules

- Column changes must preserve full previous attributes.
- Additive changes first, destructive changes only with explicit migration path.
- Backfills must be resumable for large datasets.

