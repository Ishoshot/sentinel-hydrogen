# Sentinel – Backend Architecture

This document defines the backend architecture of Sentinel.
It describes system boundaries, execution flow, and architectural principles.

All backend implementation MUST conform to this document.

---

## Architectural Overview

Sentinel’s backend is built as a **multi-tenant, event-driven system**
designed for enterprise-scale reliability, observability, and extensibility.

The backend is responsible for:

-   authentication and authorization
-   source control integrations
-   review execution
-   policy enforcement
-   usage metering
-   analytics ingestion
-   billing enforcement

---

## Technology Stack

-   Language: PHP 8.4
-   Framework: Laravel 12
-   Database: PostgreSQL 15+
-   Cache / Queue: Redis
-   AI Routing: Prism PHP
-   Workers: Laravel Horizon
-   WebSockets: Laravel Reverb
-   API Auth: Laravel Sanctum
-   OAuth: Laravel Socialite

The backend is designed to scale horizontally and operate reliably under load.

---

## High-Level System Components

### API Layer

Handles:

-   authentication
-   dashboard APIs
-   configuration management
-   webhook intake

The API layer is:

-   stateless
-   request/response only
-   free of long-running work

All expensive operations are delegated to background jobs.

---

### Worker Layer

Executes:

-   review runs
-   AI calls
-   ingestion and rollups
-   notifications
-   enforcement logic

Workers are horizontally scalable and operate independently
from the API layer.

---

### Integration Layer

Encapsulates all external systems, including:

-   source control providers
-   AI providers
-   billing systems

All integrations are accessed through interfaces and managers.

---

## Action-Based Architecture

Sentinel uses an **Action-based architecture** where business logic is encapsulated
in single-purpose Action classes.

### Key Rules

-   Controllers MUST delegate to an Action for all non-trivial flows
-   Actions represent a single business use-case
-   Actions orchestrate services, contracts, and jobs
-   Services MUST NOT coordinate multi-step workflows
-   Controllers MUST NOT call services directly for complex logic
-   Actions depend on interfaces/contracts, never concrete implementations
-   Actions are the primary unit of business-flow testing

### Action Domains

| Domain | Location | Purpose |
|--------|----------|---------|
| Reviews | `app/Actions/Reviews/` | Review execution workflow |
| Briefings | `app/Actions/Briefings/` | Report generation |
| Commands | `app/Actions/Commands/` | @sentinel command processing |
| Workspaces | `app/Actions/Workspaces/` | Workspace management |
| Teams | `app/Actions/Teams/` | Team and member management |
| Repositories | `app/Actions/Repositories/` | Repository configuration |
| Subscriptions | `app/Actions/Subscriptions/` | Billing operations |
| Installations | `app/Actions/Installations/` | GitHub App management |
| Activities | `app/Actions/Activities/` | Activity logging |
| ProviderKeys | `app/Actions/ProviderKeys/` | BYOK key management |
| SentinelConfig | `app/Actions/SentinelConfig/` | Config file sync |

---

## Domain-Driven Structure

The backend is organized by **domain**, not by technical layer alone.

Each domain owns:

-   models
-   services
-   jobs
-   events
-   policies
-   configuration

Example domains include:

-   Workspaces
-   Teams & Membership
-   Integrations
-   Repositories
-   Reviews
-   Commands (@sentinel mentions)
-   Briefings (AI-generated reports)
-   Usage & Billing
-   Analytics

---

## Execution Flow (Review Run)

1. An external event or manual trigger is received
2. The request is validated and authenticated
3. A Review Run record is created
4. A background job is dispatched
5. The worker:
    - evaluates plan limits
    - resolves eligible AI providers
    - executes the review
    - stores results
    - emits domain events
6. Results are surfaced back to the source control platform
7. Usage and analytics are recorded

No step in this flow blocks an HTTP request.

---

## Execution Flow (Command Run)

1. A GitHub comment containing `@sentinel` is received via webhook
2. The comment is parsed to extract the command and query
3. A Command Run record is created
4. A background job is dispatched
5. The worker:
    - evaluates plan limits
    - resolves eligible AI providers
    - builds context from repository (code indexing if needed)
    - executes the command using AI tools
    - stores results
    - emits domain events
6. Response is posted as a GitHub comment
7. Usage and analytics are recorded

Commands support multiple tools including code search, file reading, and web search.

---

## Event-Driven Design

Sentinel uses domain events to decouple responsibilities.

Examples:

-   `RunStarted`, `RunCompleted`, `RunFailed`
-   `CommandRunStarted`, `CommandRunCompleted`
-   `BriefingGenerationStarted`, `BriefingGenerationCompleted`
-   `InstallationConnected`, `InstallationSuspended`
-   `WorkspaceCreated`, `MemberInvited`
-   `RepositoryEnabled`, `RepositorySynced`

Events trigger listeners that perform secondary actions
without coupling to core logic.

Broadcast events (via Laravel Reverb) are used for real-time UI updates.

---

## Idempotency & Reliability

-   All webhook handlers are idempotent
-   Jobs are safe to retry
-   Duplicate events do not produce duplicate side effects
-   External calls are guarded with retries and backoff

Idempotency keys are used wherever external systems are involved.

---

## Multi-Tenancy

-   All data is scoped to a Workspace
-   Workspace boundaries are enforced at the database and application layers
-   No cross-workspace access is permitted

Tenant isolation is a core invariant.

---

## Configuration & Policy

-   Behavior is driven by configuration and policy, not hard-coded rules
-   Policies are versioned and captured per Run
-   Configuration precedence is deterministic

Configuration sources include:

-   repository config files
-   dashboard settings
-   workspace defaults

---

## AI Review Architecture

-   AI calls are routed through PrismPHP
-   Providers are selected based on eligibility rules
-   BYOK keys are required for provider usage
-   Review logic is provider-agnostic

The AI layer is treated as an external dependency, not a core system.

---

## Analytics & Ingestion

-   All review activity is recorded as append-only events
-   Rollup tables are maintained for dashboard performance
-   Analytics ingestion is asynchronous

PostgreSQL is the system of record.

---

## Observability

-   All operations emit structured logs
-   Correlation IDs are propagated across requests and jobs
-   Metrics capture:
    -   execution time
    -   failure rates
    -   queue latency
    -   usage patterns

Failures must be visible and diagnosable.

---

## Scalability Model

-   API layer scales independently of workers
-   Worker concurrency is adjustable via configuration
-   Queue priorities ensure critical jobs are processed first
-   Per-workspace throttling prevents abuse

---

## Security Principles

-   Least-privilege access to external systems
-   Secrets stored securely
-   Sensitive data is never logged
-   All inputs are validated and sanitized

Security is foundational, not optional.

---

## Guiding Principles

-   Explicit boundaries over convenience
-   Configuration over code
-   Events over coupling
-   Reliability over raw speed
-   Observability by default

---

This document defines Sentinel’s backend architecture.
Implementation details are defined in supporting backend documentation.
