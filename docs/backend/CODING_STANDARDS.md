# Sentinel – Backend Coding Standards

This document defines the coding standards for Sentinel’s backend.
All backend code MUST conform to these rules.

These standards exist to ensure:

-   long-term maintainability
-   enterprise-grade reliability
-   predictable LLM behavior
-   consistency across contributors

This document is a hard contract.

---

## Language & Framework

-   Language: PHP
-   Framework: Laravel
-   PHP features should be modern and idiomatic
-   Backward-compatibility hacks are discouraged

Laravel conventions are preferred unless explicitly overridden here.

---

## Architectural Rules

### Domain-Driven Organization

Code is organized by **domain**, not by technical layer alone.

Each domain owns:

-   models
-   actions
-   services
-   jobs
-   events
-   policies
-   tests

Cross-domain access must go through explicit interfaces.

---

### Interface-First Design

-   All external dependencies are accessed via interfaces (contracts)
-   Implementations are resolved through the service container
-   Interfaces live close to their domain

Concrete implementations must not leak across boundaries.

---

### Managers & Drivers

Use **Manager** patterns for pluggable systems, including:

-   AI providers
-   source control providers
-   notification channels

Managers:

-   resolve drivers by name
-   enforce shared contracts
-   centralize routing logic

---

## Dependency Injection

-   Constructor injection is mandatory
-   Facades are discouraged in core logic
-   Global state is forbidden

The service container is the primary mechanism for composition.

---

## Actions (Use-Case Orchestration)

Sentinel uses an **Action pattern** to model business use-cases.

An **Action** represents a single, explicit use-case
(e.g. `ExecuteReviewRun`, `EnableRepository`, `CreateWorkspace`).

Rules:

-   Controllers MUST delegate to an Action for all non-trivial flows
-   Actions orchestrate workflows and may call multiple services
-   Actions are framework-agnostic (no HTTP, request, or response logic)
-   Actions accept validated input (DTOs or value objects)
-   Actions return explicit results (DTOs or domain objects)
-   Actions may open transactions
-   Actions may dispatch jobs and domain events
-   Actions MUST depend on contracts/interfaces, never concrete implementations

### Method Naming Convention

The primary public method in an Action MUST be named `handle`.

```php
// Correct
final class CreateWorkspace
{
    public function handle(User $owner, string $name): Workspace
    {
        // ...
    }
}

// Incorrect - do not use "execute"
final class CreateWorkspace
{
    public function execute(User $owner, string $name): Workspace
    {
        // ...
    }
}
```

This convention:

-   Aligns with Laravel's job and listener patterns
-   Provides consistency across all Actions
-   Makes Actions immediately recognizable

Actions are the **primary unit for testing business flows**.

---

## Services

-   Services encapsulate **focused business logic**
-   Services are stateless
-   Services do not access HTTP or UI concerns
-   Services may dispatch events but do not handle them
-   Services are composed and orchestrated by Actions for multi-step workflows

Services must be easy to test in isolation.

---

## Controllers

Controllers:

-   are thin
-   perform validation and authorization
-   delegate work to an Action
-   return responses only

Controllers must never contain business logic.

---

## Jobs

-   Jobs perform background work
-   Jobs are idempotent
-   Jobs are retry-safe
-   Jobs contain no presentation logic

Each Job must have a single responsibility.

Jobs may be dispatched by Actions or Event Listeners.

---

## Events & Listeners

-   Events represent facts that already happened
-   Listeners perform secondary or side-effect work
-   Events must be immutable

Do not use events as commands.

---

## Authorization

-   Policies are the single source of truth for authorization
-   Authorization is Workspace-scoped
-   Never authorize based on client input alone

Authorization checks occur before Actions are executed.

---

## Error Handling

-   Fail explicitly
-   Do not swallow exceptions
-   Provide contextual error messages
-   Wrap external calls defensively

Errors must be observable and actionable.

---

## Data Access

-   Use Eloquent for standard access
-   Prefer query scopes for reuse
-   Avoid complex queries in controllers
-   Transactions must be explicit and intentional

All queries MUST be Workspace-scoped.

---

## Mutability Rules

-   Runs are immutable once completed
-   Findings are append-only
-   Historical records must not be rewritten

Immutability is required for auditability.

---

## Configuration

-   Configuration is code-driven
-   Environment-specific values live in environment files
-   Feature flags and limits are centralized

Avoid magic values in code.

---

## Testing Standards

### Testing Framework

-   Pest is mandatory
-   Tests are first-class citizens

---

### Test Types

-   **Unit tests** for services and domain logic
-   **Action tests** for business use-cases
-   **Feature tests** for API boundaries
-   **Integration tests** for critical end-to-end workflows

---

### Testing Rules

-   Tests must be deterministic
-   No shared mutable state
-   Use factories for setup
-   Mock external systems via contracts

Tests are required for all non-trivial logic.

---

## Formatting & Style

### Laravel Pint

-   Pint is mandatory
-   Code must pass formatting before merge
-   CI enforces formatting

---

### Naming Conventions

-   Classes: StudlyCase
-   Methods: camelCase
-   Variables: camelCase
-   Tables: snake_case plural

Naming must be explicit and descriptive.

---

## Automated Refactoring

### Rector

-   Rector is used for controlled refactors
-   Rector rules are versioned
-   Unsafe rules are forbidden

Rector runs must be reviewed before application.

---

## Documentation

-   Public services, actions, and interfaces must be documented
-   Complex logic requires inline explanation
-   Docs must be updated with behavior changes

Code and documentation must not drift.

---

## Performance & Safety

-   Avoid premature optimization
-   Bound all external calls
-   Protect against unbounded loops
-   Guard against N+1 queries

Correctness comes before speed.

---

## LLM Compatibility Rules

-   Code must be explicit and readable
-   Avoid clever abstractions
-   Prefer verbosity over ambiguity
-   Stable patterns are preferred

These rules exist to improve both human and AI comprehension.

---

## General Rules

-   No commented-out code
-   No dead code
-   No TODOs without context
-   No silent failures

---

This document defines Sentinel’s backend coding contract.
