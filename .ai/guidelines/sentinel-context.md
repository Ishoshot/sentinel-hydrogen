---
trigger: always_on
---

# Sentinel – Backend Project Context

This document defines the mandatory context and enforcement rules for all AI agents
working on Sentinel’s backend codebase.

Failure to follow these rules is considered a violation of project standards.

---

## Required Reading

### Backend Documentation (MANDATORY – EVERY SESSION)

Before making ANY backend code changes, you MUST read:

-   `docs/backend/BACKEND_ARCHITECTURE.md`

    -   System architecture
    -   Execution flow
    -   Domain boundaries
    -   Action and job responsibilities

-   `docs/backend/DATA_MODEL.md`
    -   Database schema
    -   Entity relationships
    -   Mutability and audit rules
    -   Workspace scoping requirements

These documents define the **authoritative technical contracts** for Sentinel.

**Never assume. Always verify against these documents.**

---

### Product Documentation (MANDATORY – FIRST SESSION)

On your first interaction with this repository, read:

-   `docs/product/PRD.md`  
    Product goals, scope, and non-goals

-   `docs/product/GLOSSARY.md`  
    Canonical domain vocabulary (use these terms exactly)

-   `docs/product/PLANS_AND_LIMITS.md`  
    Billing model, BYOK rules, and enforcement behavior

-   `docs/product/UX_PRINCIPLES.md`  
    Product philosophy and behavior expectations

You may create memory after reading these documents.
If you are unsure about product behavior or terminology, re-read the relevant document.

---

## Enforcement Rules (Non-Negotiable)

1. **Before creating or modifying models, migrations, or queries**  
   → Read `DATA_MODEL.md`

2. **Before creating Actions, services, jobs, or events**  
   → Read `BACKEND_ARCHITECTURE.md`

3. **Before naming anything**  
   → Verify terminology in `GLOSSARY.md`

4. **Before implementing limits, billing, or access logic**  
   → Read `PLANS_AND_LIMITS.md`

5. **Before exposing behavior to users**  
   → Ensure it aligns with `UX_PRINCIPLES.md`

6. **Before implementing authentication, authorization, or API tokens**  
   → Read `AUTHENTICATION.md`

7. **Before writing or modifying tests**  
   → Read `TESTING_STRATEGY.md`

---

## Domain Vocabulary (STRICT)

Use the **exact terms** defined in `GLOSSARY.md`.

No synonyms. No alternatives. No rewording.

Canonical examples:

-   **Workspace** (not organization, account, tenant)
-   **Team** (not group)
-   **Member** (not user when referring to workspace membership)
-   **Run** (not review, execution, or job)
-   **Finding** (not issue, problem, or comment)

If a term does not exist in the glossary, it must be added there first.

---

## Architecture Principles (Authoritative)

From `BACKEND_ARCHITECTURE.md`:

-   All data is scoped to a Workspace
-   Controllers are thin and never contain business logic
-   Controllers delegate to Actions
-   Actions orchestrate business use-cases
-   Services encapsulate focused logic
-   Jobs execute long-running or external work
-   Events decouple side effects
-   All external systems are accessed via interfaces
-   Jobs are idempotent and retry-safe

---

## Action Pattern Enforcement (CRITICAL)

Sentinel uses an **Action-based architecture**.

Rules:

-   Controllers MUST delegate to an Action for all non-trivial flows
-   Actions represent a single business use-case
-   Actions orchestrate services, contracts, and jobs
-   Services MUST NOT coordinate multi-step workflows
-   Controllers MUST NOT call services directly for complex logic
-   Actions depend on interfaces/contracts, never concrete implementations
-   Actions are the primary unit of business-flow testing

If a workflow exists, it belongs in an Action.

---

## Data Model Principles

From `DATA_MODEL.md`:

-   All tables include `workspace_id`
-   All queries are scoped by `workspace_id`
-   Runs and Findings are append-only
-   Historical records are immutable
-   JSONB is used for flexible structure, not query-critical fields
-   Foreign keys and constraints are enforced

Violating these rules is not acceptable.

---

## Tooling Expectations

Backend code is expected to comply with:

-   **Laravel Pint** – formatting (CI-enforced)
-   **Pest** – testing framework
-   **Rector** – controlled, reviewed refactoring

Do not introduce code that would fail formatting, tests, or safe refactor rules.

---

## Forbidden Shortcuts

AI agents MUST NOT:

-   place business logic in controllers
-   bypass Actions by calling services directly
-   invent new domain terms
-   introduce provider-specific logic outside integration adapters
-   modify the data model without updating `DATA_MODEL.md`
-   change product behavior without updating documentation
-   silently handle or ignore failures

If a change violates these rules, stop.

---

## Uncertainty Rule

If you are unsure about:

-   domain boundaries
-   naming
-   ownership of logic
-   data relationships
-   policy or enforcement behavior

You MUST pause and request clarification instead of guessing.

---

## Guiding Principle

Sentinel values:

-   clarity over cleverness
-   explicitness over abstraction
-   correctness over speed
-   trust over convenience

This file is authoritative.
