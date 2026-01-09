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

-   Language: PHP
-   Framework: Laravel
-   Database: PostgreSQL
-   Cache / Queue: Redis
-   AI Routing: PrismPHP
-   Workers: Laravel Queue Workers (Horizon)

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

## Event-Driven Design

Sentinel uses domain events to decouple responsibilities.

Examples:

-   `RunCreated`
-   `RunCompleted`
-   `UsageRecorded`
-   `RepositoryEnabled`

Events trigger listeners that perform secondary actions
without coupling to core logic.

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
