# Sentinel – Data Model

This document defines the core data model for Sentinel.
It describes entities, relationships, and data management rules.

All database design and access MUST conform to this document.

---

## Database Philosophy

Sentinel uses **PostgreSQL** as the system of record.

The data model is designed to be:

-   multi-tenant
-   auditable
-   append-oriented
-   scalable over time
-   friendly to analytics and rollups

PostgreSQL is authoritative.
No document-oriented database is used.

---

## Tenant Boundary

### Workspace

The Workspace is the primary tenant boundary.

All tables that store user or operational data MUST include:

-   `workspace_id`

All queries MUST be scoped by `workspace_id`.

Cross-workspace access is forbidden.

---

## Core Identity Tables

### users

Represents authenticated individuals.

Key fields:

-   id
-   name
-   email
-   avatar_url
-   created_at
-   updated_at

Users may belong to multiple Workspaces.

---

### workspaces

Represents a tenant.

Key fields:

-   id
-   name
-   owner_user_id
-   plan_id
-   subscription_status
-   trial_ends_at
-   created_at
-   updated_at

A Workspace owns all related data.

---

### teams

Represents the membership container for a Workspace.

Key fields:

-   id
-   workspace_id
-   name
-   created_at
-   updated_at

Each Workspace has exactly one Team.

---

### team_members

Represents membership of Users in a Team.

Key fields:

-   id
-   team_id
-   user_id
-   role
-   created_at
-   updated_at

---

### invitations

Represents pending invitations to join a Team.

Key fields:

-   id
-   team_id
-   email
-   role
-   token
-   expires_at
-   created_at

---

## Integration Tables

### providers

Represents supported external platforms.

Key fields:

-   id
-   name
-   slug
-   created_at

---

### connections

Represents authorization state between a Workspace and a Provider.

Key fields:

-   id
-   workspace_id
-   provider_id
-   status
-   metadata (JSONB)
-   created_at
-   updated_at

---

### installations

Represents an installed instance of Sentinel within a Provider account.

Key fields:

-   id
-   workspace_id
-   provider_id
-   external_id
-   metadata (JSONB)
-   created_at
-   updated_at

---

### repositories

Represents a source code repository.

Key fields:

-   id
-   workspace_id
-   installation_id
-   external_id
-   name
-   default_branch
-   is_enabled
-   created_at
-   updated_at

---

### repository_settings

Represents configuration for a specific Repository.

Key fields:

-   id
-   repository_id
-   settings (JSONB)
-   created_at
-   updated_at

---

## Review System Tables

### runs

Represents a single review execution.

Runs are append-only and immutable once completed.

Key fields:

-   id
-   workspace_id
-   repository_id
-   external_reference
-   status
-   started_at
-   completed_at
-   metrics (JSONB)
-   policy_snapshot (JSONB)
-   metadata (JSONB)
-   created_at

Indexes:

-   workspace_id, created_at
-   repository_id, created_at

---

### findings

Represents issues identified during a Run.

Key fields:

-   id
-   run_id
-   finding_hash
-   workspace_id
-   severity
-   category
-   title
-   description
-   file_path
-   line_start
-   line_end
-   confidence
-   metadata (JSONB)
-   created_at

Findings may or may not be surfaced externally.

---

### annotations

Represents surfaced feedback in the source control platform.

Key fields:

-   id
-   finding_id
-   workspace_id
-   provider_id
-   external_id
-   type
-   created_at

---

## Command System Tables

### command_runs

Represents a single @sentinel command execution.

Key fields:

-   id
-   workspace_id
-   repository_id
-   external_reference
-   github_comment_id
-   issue_number
-   is_pull_request
-   command_type
-   query
-   status
-   started_at
-   completed_at
-   duration_seconds
-   response (JSONB)
-   context_snapshot (JSONB)
-   metrics (JSONB)
-   metadata (JSONB)
-   created_at

Indexes:

-   workspace_id, created_at
-   repository_id, created_at
-   status
-   command_type
-   github_comment_id
-   unique: workspace_id, external_reference

---

## Briefings Tables

### briefings

Represents Briefing templates (system or custom).

Key fields:

-   id
-   workspace_id (nullable, null = system template)
-   title
-   slug (unique)
-   description
-   icon
-   target_roles (JSONB)
-   parameter_schema (JSONB)
-   prompt_path
-   requires_ai
-   eligible_plan_ids (JSONB)
-   output_formats (JSONB)
-   is_schedulable
-   is_system
-   sort_order
-   is_active
-   created_at
-   updated_at

---

### briefing_generations

Represents generated Briefing instances.

Key fields:

-   id
-   workspace_id
-   briefing_id
-   generated_by_id
-   parameters (JSONB)
-   status (pending, processing, completed, failed)
-   progress
-   progress_message
-   started_at
-   completed_at
-   narrative (text)
-   structured_data (JSONB)
-   achievements (JSONB)
-   excerpts (JSONB)
-   output_paths (JSONB)
-   metadata (JSONB)
-   error_message
-   expires_at
-   created_at

Indexes:

-   workspace_id, created_at DESC
-   workspace_id, briefing_id, created_at DESC
-   status
-   expires_at

---

### briefing_subscriptions

Represents scheduled recurring Briefing generations.

Key fields:

-   id
-   workspace_id
-   user_id
-   briefing_id
-   schedule_preset (daily, weekly, monthly)
-   schedule_day
-   schedule_hour
-   parameters (JSONB)
-   delivery_channels (JSONB)
-   slack_webhook_url (encrypted)
-   last_generated_at
-   next_scheduled_at
-   is_active
-   created_at
-   updated_at

---

### briefing_shares

Represents external share links.

Key fields:

-   id
-   briefing_generation_id
-   workspace_id
-   created_by_id
-   token (unique, 64 chars)
-   password_hash
-   access_count
-   max_accesses
-   expires_at
-   is_active
-   created_at

---

### briefing_downloads

Tracks download/access events for analytics.

Key fields:

-   id
-   briefing_generation_id
-   workspace_id
-   user_id (nullable, null for external shares)
-   format (html, pdf, markdown, slides)
-   source (dashboard, share_link, api, email)
-   ip_address
-   user_agent
-   downloaded_at

---

## Activity Tables

### activities

Represents logged events within a Workspace.

Key fields:

-   id
-   workspace_id
-   user_id (nullable, null for system actions)
-   type
-   subject_type
-   subject_id
-   description
-   metadata (JSONB)
-   created_at

Indexes:

-   workspace_id, created_at DESC
-   workspace_id, type
-   subject_type, subject_id

---

## Policy & Configuration Tables

### policies

Represents named policy configurations.

Key fields:

-   id
-   workspace_id
-   name
-   config (JSONB)
-   version
-   created_at

---

Policy snapshots are stored directly on Runs for auditability.

---

## Usage & Billing Tables

### plans

Represents available subscription plans.

Key fields:

-   id
-   tier (foundation, illuminate, orchestrate, sanctum)
-   description
-   monthly_runs_limit
-   monthly_commands_limit
-   team_size_limit
-   features (JSONB)
-   limits (JSONB)
-   price_monthly (cents)
-   price_yearly (cents)
-   created_at
-   updated_at

---

### subscriptions

Represents a Workspace’s active subscription.

Key fields:

-   id
-   workspace_id
-   plan_id
-   status
-   started_at
-   ends_at
-   stripe_customer_id
-   stripe_subscription_id
-   created_at
-   updated_at

---

### usage_records

Represents metered usage totals for a billing period.

Key fields:

-   id
-   workspace_id
-   period_start
-   period_end
-   runs_count
-   commands_count
-   briefings_count
-   findings_count
-   annotations_count
-   tokens_estimated
-   created_at
-   updated_at

---

### provider_keys

Represents BYOK credentials for AI providers.

Key fields:

-   id
-   workspace_id
-   provider
-   encrypted_key
-   created_at
-   updated_at

Provider keys are encrypted at rest.

---

### provider_models

Represents the global catalog of AI provider models available for BYOK.

This is reference data managed by Sentinel (not workspace-scoped).

Key fields:

-   id
-   provider
-   identifier
-   name
-   description
-   is_default
-   is_active
-   sort_order
-   context_window_tokens
-   max_output_tokens
-   created_at
-   updated_at

---

## Analytics & Rollups

### workspace_daily_metrics

Represents precomputed aggregates for dashboards.

Key fields:

-   workspace_id
-   date
-   runs_count
-   findings_count
-   critical_count
-   warning_count
-   tokens_estimated

These tables are updated asynchronously.

---

## Data Integrity Rules

-   Foreign keys are enforced where appropriate
-   Deletions are soft where data must be retained
-   Runs and Findings are never updated in-place
-   Sensitive fields are encrypted

---

## Scaling & Partitioning Strategy

-   Runs and Findings may be partitioned by time
-   Indexes prioritize tenant and time-based access
-   Rollups reduce pressure on raw tables

---

## General Rules

-   JSONB is used for flexible, non-critical structure
-   Queryable fields must be normalized
-   Schema changes require migrations
-   Backfills must be idempotent

---

This document defines Sentinel’s authoritative data model.
