# Sentinel – Queues and Jobs

This document defines how background work is executed in Sentinel.
It describes queue structure, job responsibilities, retry behavior,
and scaling rules.

All asynchronous processing MUST conform to this document.

---

## Purpose of Background Jobs

Sentinel uses background jobs to ensure:

-   fast and predictable API responses
-   reliable execution of long-running tasks
-   isolation between user-facing requests and heavy processing
-   scalable and observable execution

No long-running or external operations are permitted in HTTP requests.

---

## Queue System

Sentinel uses:

-   Redis as the queue backend
-   Laravel Queue Workers
-   Laravel Horizon for monitoring and scaling

Queues are the primary execution mechanism for Sentinel’s backend.

---

## Queue Topology

Queues are separated by priority and responsibility.
All queue names are defined in `App\Enums\Queue\Queue`.
Lower priority values are processed first (higher priority).

### system (Priority 1)

Handles critical internal system tasks.

---

### webhooks (Priority 5)

Handles inbound events from external providers.

Characteristics:

-   lightweight
-   fast validation only
-   no external calls

---

### reviews-enterprise (Priority 20)

Handles review executions for enterprise-tier workspaces.

-   Highest priority review queue
-   Dedicated workers for enterprise customers

---

### reviews-paid (Priority 30)

Handles review executions for paid-tier workspaces (pro, team).

-   Mid-priority review queue
-   Dedicated workers for paying customers

---

### reviews-default (Priority 40)

Handles standard automated review runs for free-tier workspaces.

-   Lowest priority review queue
-   Shared workers

---

### briefings-default (Priority 45)

Handles briefing generation jobs.

---

### commands (Priority 48)

Handles command execution jobs.

---

### annotations (Priority 50)

Handles posting review findings as GitHub PR comments.

---

### notifications (Priority 55)

Handles outbound notifications and callbacks.

Examples:

-   posting results to source control platforms
-   sending emails or webhooks (future)

---

### sync (Priority 70)

Handles synchronization tasks between Sentinel and external providers.

---

### default (Priority 80)

Handles general-purpose background work that does not fit a specialized queue.

---

### code-indexing (Priority 85)

Handles code indexing operations for repository analysis.

---

### long-running (Priority 90)

Handles long-running operations that may take several minutes.

---

### bulk (Priority 100)

Handles bulk operations that process large volumes of data.

---

## Job Responsibilities

Each Job MUST:

-   perform a single, well-defined task
-   be idempotent
-   be safe to retry
-   emit structured logs
-   respect workspace limits and policies

Jobs must not coordinate complex workflows internally.
Workflow orchestration belongs in higher-level services.

---

## Review Execution Jobs

### ExecuteReviewRun

Responsible for executing a single review Run.

Responsibilities:

-   evaluate subscription and plan limits
-   resolve eligible AI providers
-   invoke AI review engine
-   persist ReviewResult
-   emit domain events

This job runs on the 3-tier review queue system: `reviews-enterprise`, `reviews-paid`, or `reviews-default`, depending on the workspace's subscription tier.

---

## Webhook Jobs

### ProcessWebhookEvent

Responsible for handling incoming provider events.

Responsibilities:

-   validate authenticity
-   deduplicate events
-   enqueue downstream jobs

Webhook jobs must not perform AI calls.

---

## Retry & Failure Handling

### Retry Policy

-   Jobs define explicit retry counts
-   Exponential backoff is preferred
-   External calls must be retried safely

---

### Failure Visibility

-   Failures are logged with correlation IDs
-   Failed jobs are visible in Horizon
-   Permanent failures emit explicit events

Silent failures are not acceptable.

---

## Idempotency

All jobs MUST be idempotent.

Common strategies:

-   idempotency keys
-   unique constraints
-   existence checks
-   atomic updates

Duplicate job execution must not result in duplicate side effects.

---

## Throttling & Fairness

-   Per-workspace throttling is enforced
-   No single workspace may starve the system
-   Rate limits are applied before execution

---

## Scaling Model

-   Workers scale horizontally
-   Queue priorities ensure responsiveness
-   Worker concurrency is configurable
-   Horizon configuration is version-controlled

API and worker layers scale independently.

---

## Observability

Each job must:

-   log start and completion
-   log failures explicitly
-   include correlation identifiers
-   emit timing metrics

Job behavior must be diagnosable in production.

---

## General Rules

-   No synchronous job dispatch from jobs unless explicitly designed
-   No cross-workspace job coordination
-   No job performs UI or formatting logic
-   All side effects must be explicit

---

This document defines Sentinel’s queue and job execution contract.
