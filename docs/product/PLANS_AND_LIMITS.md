# Sentinel – Plans and Limits

This document defines Sentinel’s subscription model, access rules, and enforcement behavior.

All billing, usage enforcement, dashboards, and backend logic MUST conform to this document.
Any changes to plans or limits must be reflected here first.

---

## Billing Philosophy

Sentinel uses a **subscription-based access model** combined with a **Bring-Your-Own-Key (BYOK)** approach for AI usage.

-   Customers pay Sentinel for **access, features, and governance**
-   Customers supply their own API keys for AI providers
-   Sentinel does **not** resell AI usage by default

This model prioritizes transparency, cost control, and enterprise trust.

---

## Subscription Model

### Plan

A **Plan** defines the maximum capabilities available to a Workspace.

Plans control:

-   number of enabled repositories
-   number of provider installations
-   feature availability
-   enforcement behavior when limits are reached

Plans do **not** include AI usage by default.

Current subscription tiers:

| Plan           | Monthly Price | Runs/Month | Team Size   | Support   |
| -------------- | ------------- | ---------- | ----------- | --------- |
| **Foundation** | $0            | 20         | 2 Members   | Community |
| **Illuminate** | $20           | 500        | 5 Members   | Email     |
| **Orchestrate**| $50           | 2,000      | Unlimited   | Priority  |
| **Sanctum**    | $200          | Unlimited  | Unlimited   | Dedicated |

---

### Subscription

A **Subscription** represents the active billing state of a Workspace.

-   A Workspace must have an active Subscription to use Sentinel
-   If a Subscription is inactive, review execution is disabled
-   Historical data remains accessible unless explicitly restricted

---

## Core Limits

Plans may define one or more of the following limits.

### Workspace Creation Rules

Users may create multiple workspaces subject to the following rules:

-   The first workspace is always allowed (can be on any plan, including free)
-   To create additional workspaces, ALL existing workspaces must be on a paid plan (Illuminate or higher)
-   If any existing workspace is on the free Foundation plan, new workspace creation is blocked
-   This ensures users are paying customers before allowing multiple workspaces

---

### Repository Limit (Not Enforced)

> **Note:** This limit is not enforced and is not a planned feature.

The maximum number of repositories that may be enabled for reviews within a Workspace.

-   Disabled repositories do not count toward this limit
-   Enabling a repository beyond the limit is blocked

---

### Installation Limit (Not Enforced)

> **Note:** This limit is not enforced and is not a planned feature.

The maximum number of provider installations allowed per Workspace.

-   Installations beyond the limit cannot be connected
-   Existing installations are not automatically removed

---

### Execution Limit (Optional)

A maximum number of review Runs allowed within a given period.

-   Enforcement behavior is plan-dependent
-   Limits are evaluated at execution time

---

## Feature Access

Plans may enable or restrict access to specific features, including but not limited to:

-   manual review triggers
-   advanced dashboards
-   repository-level configuration
-   configuration-as-code support
-   extended data retention

Plan feature flags are enforced for:

-   BYOK provider keys
-   custom repository guidelines
-   priority review queues
-   API token access
-   single sign-on
-   audit log retention

Feature availability is enforced consistently across the application.

---

## AI Usage Model (BYOK)

### Provider Keys

-   Each Workspace may configure one or more Provider Keys
-   Provider Keys are scoped to the Workspace
-   Provider Keys determine which AI providers are eligible for routing

Sentinel will never route a review to a provider
for which no Provider Key is configured.

---

### Routing Eligibility

Before executing a review, Sentinel evaluates:

1. Workspace subscription state
2. Plan limits
3. Provider key availability
4. Provider eligibility rules

If no eligible provider is available, the Run is skipped gracefully.

---

### Cost Responsibility

-   All AI usage costs are incurred directly by the customer
-   Sentinel does not add markup or intermediary billing by default
-   Sentinel may estimate usage for reporting and visibility

---

## Enforcement Behavior

### Hard Limits

When a hard limit is reached:

-   new executions are blocked
-   no AI calls are made
-   Sentinel reports the reason clearly to the user

---

### Soft Limits (Optional)

Plans may define soft limits that:

-   allow execution to continue
-   surface warnings in dashboards
-   encourage plan upgrades

---

### Graceful Degradation

When limits are reached or providers are unavailable:

-   Sentinel does not fail silently
-   Sentinel records the attempted Run
-   Sentinel reports the outcome clearly in the UI and source control platform

---

## Trial Access

Trials, if offered, are defined by:

-   time-based access
-   or limited execution counts

Trial behavior must:

-   respect Provider Key availability
-   enforce execution limits
-   clearly communicate expiration

---

## Plan Changes

### Upgrades

-   Upgrades take effect immediately
-   Newly available limits and features become accessible at once

---

### Downgrades

-   Downgrades take effect at the next billing cycle
-   Existing resources are not removed automatically
-   New actions are blocked if they exceed the downgraded plan limits

---

## Data Retention

Plans may define data retention policies, including:

-   how long Runs and Findings are retained
-   access to historical analytics

Retention behavior must be enforced consistently.

---

## General Rules

-   All limit checks occur server-side
-   Client-side indicators are informational only
-   Enforcement logic must be deterministic and auditable
-   Limits are evaluated per Workspace

---

This document defines Sentinel’s access and usage contract.
