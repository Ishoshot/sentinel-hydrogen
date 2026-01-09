# Sentinel – Observability

This document defines the observability standards for Sentinel’s backend.
It covers logging, metrics, tracing, and failure visibility.

All backend components MUST conform to this document.

---

## Observability Philosophy

Sentinel is designed to be **observable by default**.

If a system cannot explain:

-   what happened
-   when it happened
-   why it happened
-   and for whom it happened

then it is considered broken.

Observability is not optional.

---

## Core Principles

-   Every action is attributable
-   Every failure is visible
-   Every execution is traceable
-   Silence is treated as a bug

---

## Correlation & Traceability

### Correlation ID

-   Every inbound request is assigned a `correlation_id`
-   The `correlation_id` is propagated through:
    -   HTTP requests
    -   background jobs
    -   external API calls
    -   domain events
-   The same `correlation_id` must appear in all related logs

Correlation IDs enable end-to-end traceability.

---

## Logging

### Structured Logging

All logs MUST:

-   be structured (JSON)
-   include `correlation_id`
-   include `workspace_id` where applicable
-   include contextual metadata

Logs are written for machines first, humans second.

---

### Log Levels

-   **DEBUG**  
    Development-only diagnostics

-   **INFO**  
    Normal system behavior (job start/end, state transitions)

-   **WARNING**  
    Recoverable issues, retries, soft failures

-   **ERROR**  
    Failed operations requiring attention

-   **CRITICAL**  
    System-level failures or data integrity risks

Log levels must be used consistently.

---

### What Must Be Logged

At minimum:

-   job start and completion
-   job failures
-   external API failures
-   policy enforcement decisions
-   limit enforcement outcomes
-   integration state changes

Sensitive data MUST NOT be logged.

---

## Metrics

### Required Metrics

Sentinel MUST capture metrics for:

-   review execution duration
-   queue latency
-   job success and failure rates
-   provider error rates
-   usage volume
-   throttling events

Metrics are used for:

-   dashboards
-   alerting
-   capacity planning

---

### Metric Dimensions

Metrics SHOULD be tagged by:

-   workspace_id
-   provider
-   job type
-   status

Metric cardinality must be controlled carefully.

---

## Tracing

Where supported:

-   External API calls are wrapped with trace spans
-   Long-running operations are traceable
-   Provider latency is measurable

Tracing complements logs and metrics.

---

## Failure Visibility

### Explicit Failures

Failures MUST:

-   be recorded as first-class events
-   include error context
-   be surfaced in internal dashboards

Silent failures are forbidden.

---

### Graceful Degradation

When failures occur:

-   Sentinel records the failure
-   Sentinel explains the impact
-   Sentinel avoids cascading errors

---

## Auditability

Sentinel maintains auditability through:

-   immutable Run records
-   stored Policy Snapshots
-   persisted Review Results
-   explicit state transitions

Historical behavior must be reconstructable.

---

## Alerting (Future)

Alerting rules may be defined for:

-   sustained job failures
-   provider outages
-   queue backlogs
-   abnormal error rates

Alerting must be actionable.

---

## Operational Visibility

Operators must be able to answer:

-   Which workspaces are affected?
-   Which providers are failing?
-   What changed recently?
-   Is this isolated or systemic?

The system must make these answers accessible.

---

## Tooling Expectations

Sentinel assumes integration with:

-   centralized log aggregation
-   metrics collection systems
-   error tracking tools

Exact tooling may vary, but standards do not.

---

## General Rules

-   No catch-and-ignore
-   No swallowing exceptions
-   No logging without context
-   No metrics without meaning

---

This document defines Sentinel’s observability contract.
