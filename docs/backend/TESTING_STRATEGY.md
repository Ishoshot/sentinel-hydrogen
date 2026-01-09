# Sentinel – Testing Strategy

This document defines how testing is approached in Sentinel.
It covers test types, organization, tooling, and quality standards.

All tests MUST conform to this document.
This is a hard contract.

---

## Testing Philosophy

Sentinel treats tests as **first-class citizens**.

Tests are:

-   mandatory for all non-trivial code
-   part of the definition of done
-   run on every commit
-   blocking for merge

Untested code is incomplete code.

---

## Testing Framework

### Pest

Sentinel uses **Pest** as the primary testing framework.

Pest provides:

-   expressive, readable syntax
-   first-class Laravel integration
-   architecture testing
-   type coverage analysis
-   parallel execution

All new tests MUST be written in Pest.

---

## Test Types

### Unit Tests

**Purpose**: Test isolated logic without external dependencies.

**Location**: `tests/Unit/`

**Scope**:

-   value objects
-   DTOs
-   pure functions
-   domain logic
-   utility classes

**Rules**:

-   no database access
-   no HTTP requests
-   no external services
-   fast execution

---

### Feature Tests

**Purpose**: Test application behavior through HTTP or console boundaries.

**Location**: `tests/Feature/`

**Scope**:

-   API endpoints
-   controller behavior
-   middleware
-   request validation
-   response structure
-   side effects

**Rules**:

-   use `RefreshDatabase`
-   test full request/response lifecycle
-   assert status codes and response structure
-   verify side effects (jobs, events, persistence)

---

### Action Tests

**Purpose**: Test business use-cases in isolation.

**Location**:

-   `tests/Feature/Actions/`
-   or `tests/Unit/Actions/` (when no persistence is involved)

**Scope**:

-   Action classes
-   workflow orchestration
-   service coordination
-   event and job dispatching

**Rules**:

-   mock external dependencies via contracts
-   verify outcomes, not implementation details
-   test happy paths, failure paths, and edge cases
-   Actions are the primary unit of business testing

---

### Integration Tests

**Purpose**: Validate critical end-to-end workflows.

**Location**: `tests/Integration/`

**Scope**:

-   multi-step workflows
-   integration boundaries
-   queue processing chains
-   event propagation

**Rules**:

-   use sparingly
-   focus on critical paths only
-   external services may be used **only in sandbox mode**
-   external API usage must be explicitly documented
-   integration tests MUST NOT run by default in CI

---

### Architecture Tests

**Purpose**: Enforce structural and design rules.

**Location**:

-   `tests/ArchTest.php`
-   `tests/Arch/`

**Scope**:

-   namespace boundaries
-   dependency direction
-   architectural constraints
-   naming conventions
-   forbidden patterns

**Rules**:

-   run on every commit
-   violations block merge
-   presets for PHP, Laravel, and security are enforced

---

## Test Organization

### Directory Structure

ests/
├── Arch/ # Architecture tests by domain
│ ├── ModelsTest.php
│ ├── HttpTest.php
│ └── ...
├── Feature/ # Feature tests
│ ├── Actions/ # Action tests
│ ├── Api/ # API endpoint tests
│ └── ...
├── Integration/ # Integration tests
├── Unit/ # Unit tests
├── ArchTest.php # Global architecture tests
├── Pest.php # Pest configuration
└── TestCase.php # Base test case

---

## Naming Conventions

-   Test files: `{Subject}Test.php`
-   Test descriptions must be behavior-focused

Examples:

````php
it('creates a workspace for the authenticated user');
it('rejects invalid repository configurations');
it('dispatches review job when trigger is received');

## Workspace Scoping Enforcement (CRITICAL)

All tests involving persisted data MUST:

- create an explicit Workspace
- associate all models with that Workspace
- assert workspace isolation where applicable

Tests MUST NOT rely on implicit or default workspace behavior.

If a test passes without a Workspace context, it is invalid.

---

## Factories

### Factory Design

Factories are mandatory for all models.

**Location:** `database/factories/`

**Rules:**
- every model has a factory
- factories produce valid, minimal data
- use states for variations
- avoid over-specification

---

### Factory States

Use states to express intent:

```php
User::factory()->admin()->create();
Repository::factory()->enabled()->create();
Run::factory()->completed()->create();
```

---

### Factory Relationships

Factories must handle relationships explicitly:

```php
Run::factory()
    ->for(Repository::factory())
    ->has(Finding::factory()->count(3))
    ->create();
```

---

## Mocking Strategy

### External Dependencies

External systems MUST be mocked via contracts:

-   AI providers
-   source control providers
-   billing systems
-   notification systems

Never call real external services in unit or feature tests.

---

### Contract-Based Mocking

Mock interfaces, not implementations:

```php
$this->mock(SourceControlContract::class)
    ->shouldReceive('fetchPullRequest')
    ->andReturn($fakePullRequest);
```

---

### Fakes Over Mocks

Prefer Laravel fakes where available:

```php
Event::fake();
Queue::fake();
Notification::fake();
Storage::fake();
```

---

## Queue & Job Testing

### Job Dispatch

Verify dispatch behavior:

```php
Queue::fake();

// trigger action

Queue::assertPushed(ExecuteReviewRun::class);
```

---

### Job Execution

Test job behavior directly by invoking handlers:

```php
it('executes review and stores results', function () {
    $run = Run::factory()->create();

    (new ExecuteReviewRun($run))->handle();

    expect($run->fresh()->status)->toBe('completed');
});
```

---

### Queue Execution Rules

-   Feature and Action tests SHOULD fake queues by default
-   Job logic is tested by invoking handlers directly
-   Integration tests may allow real queue processing explicitly
-   Horizon and Redis are never required for unit or feature tests
-   Queue behavior must be deterministic in tests

---

## Event Testing

### Event Dispatch

```php
Event::fake();

// trigger action

Event::assertDispatched(RunCompleted::class);
```

---

### Event Listeners

Test listeners in isolation:

```php
it('updates rollups when run completes', function () {
    $event = new RunCompleted($run);

    (new UpdateWorkspaceRollups())->handle($event);

    // assert rollup updated
});
```

---

## Authorization & Policy Testing

Authorization MUST be tested explicitly.

Tests should cover:

-   workspace membership enforcement
-   role-based access
-   plan and feature gating
-   token ability restrictions

Policy tests are required for all protected Actions.

---

## API Testing

### Authentication

For session-authenticated requests:

```php
$this->actingAs($user)->getJson('/api/me');
```

For Sanctum tokens:

```php
Sanctum::actingAs($user, ['read:repositories']);
```

---

### Response Assertions

Use semantic assertions:

-   `assertOk()`
-   `assertCreated()`
-   `assertForbidden()`
-   `assertNotFound()`
-   `assertUnprocessable()`

Avoid numeric status codes where semantic assertions exist.

---

## Coverage Requirements

### Code Coverage

-   Minimum: 80%
-   Target: 100% for critical paths
-   Enforced via CI

Critical paths include:

-   authentication and authorization
-   billing and plan enforcement
-   review execution and persistence
-   provider integration boundaries

---

### Type Coverage

-   Minimum: 100%
-   Enforced via `pest --type-coverage`

---

### Mutation Testing (Future)

Mutation testing may be introduced for critical logic.

---

## CI Integration

### Pipeline Steps

1. Install dependencies
2. Run Pint
3. Run static analysis
4. Run architecture tests
5. Run unit tests
6. Run feature tests
7. Generate coverage reports

---

### Blocking Rules

PRs are blocked if:

-   any test fails
-   coverage drops below thresholds
-   formatting violations exist
-   architectural rules are violated

---

## Test Quality Rules

Tests MUST be:

-   **deterministic**
-   **isolated**
-   **fast**
-   **readable**
-   **intention-revealing**

Tests MUST NOT:

-   depend on execution order
-   share mutable state
-   sleep or wait arbitrarily
-   test implementation details
-   duplicate coverage unnecessarily

---

## General Rules

-   every PR includes relevant tests
-   tests are reviewed with the same rigor as production code
-   flaky tests are fixed immediately
-   test failures block deployment

---

This document defines Sentinel's testing contract.
````
