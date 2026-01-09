# Sentinel – Integrations

This document defines how Sentinel integrates with external systems.
It establishes architectural boundaries, lifecycle rules, and extensibility guidelines.

All integrations MUST conform to this document.

---

## Integration Philosophy

Sentinel is **platform-agnostic by design**.

Integrations are treated as:

-   replaceable
-   isolated
-   interface-driven
-   explicitly configured

No integration is considered a core dependency.
The system must continue to operate gracefully when integrations fail or are unavailable.

---

## Integration Categories

Sentinel integrations fall into three primary categories:

1. Source Control Providers
2. AI Providers
3. Billing and External Services (future)

Each category follows consistent design principles.

---

## Source Control Providers

### Definition

A **Source Control Provider** represents a platform that hosts repositories
and emits events related to code changes.

Examples:

-   GitHub (current)
-   GitLab (future)

---

### Provider Abstraction

All source control providers implement a shared interface.

Responsibilities include:

-   authentication and authorization
-   installation lifecycle management
-   repository discovery
-   webhook event normalization
-   annotation and comment publishing

Provider-specific logic MUST NOT leak outside the integration layer.

---

### Installation Lifecycle

An Installation represents Sentinel being installed into a provider account.

Lifecycle stages:

1. Installation created
2. Permissions granted
3. Repositories selected
4. Installation activated
5. Installation suspended or removed

Installation state changes are persisted and auditable.

---

### Webhook Handling

-   All inbound webhooks are verified
-   Webhooks are normalized into internal events
-   Duplicate events are safely ignored
-   Webhooks enqueue jobs; they do not perform work directly

Webhook payloads are never trusted implicitly.

---

## AI Providers

### Definition

An **AI Provider** represents an external system capable of performing code analysis
or review tasks.

Examples:

-   OpenAI
-   Anthropic
-   Others supported via PrismPHP

---

### Provider Routing

AI providers are accessed exclusively through **PrismPHP**.

Routing decisions are based on:

-   workspace configuration
-   provider key availability
-   policy constraints
-   provider health and eligibility

Sentinel never hard-codes provider-specific logic.

---

### BYOK Enforcement

-   All AI providers require a valid Provider Key
-   Provider Keys are workspace-scoped
-   Providers without keys are excluded from routing

No fallback provider is assumed.

---

### Failure Handling

If an AI provider:

-   times out
-   returns invalid output
-   becomes unavailable

Sentinel:

-   records the failure
-   produces a ReviewResult with failure context
-   does not retry indefinitely
-   does not switch providers silently unless policy allows

Failures must be explicit and visible.

---

## Integration Configuration

### Workspace-Level Configuration

Defines:

-   enabled providers
-   provider credentials
-   default behavior

---

### Repository-Level Configuration

Defines:

-   review enablement
-   thresholds and limits
-   overrides to workspace defaults

---

### Configuration Precedence

1. Repository configuration file (if present)
2. Repository settings (dashboard)
3. Workspace defaults
4. System defaults

This order is fixed and deterministic.

---

## Security Considerations

-   Secrets are encrypted at rest
-   Access tokens are rotated when possible
-   Webhook secrets are validated
-   Least-privilege scopes are enforced

Integration failures must never expose sensitive data.

---

## Extensibility Rules

When adding a new integration:

-   implement the appropriate interface
-   register via a Service Provider
-   avoid modifying existing integrations
-   add tests and documentation

New integrations must not require changes to core domains.

---

## Observability

Integration operations must:

-   emit structured logs
-   include correlation IDs
-   record latency and error rates

Integration health is observable and measurable.

---

## General Rules

-   No integration logic in controllers
-   No provider-specific branching outside adapters
-   No implicit assumptions about provider behavior
-   All external calls are bounded and timeout-protected

---

This document defines Sentinel’s integration contract.
