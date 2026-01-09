# Sentinel – Product Glossary

This glossary defines the canonical domain vocabulary used across Sentinel.
All frontend copy, backend code, database models, APIs, documentation, and AI agents
MUST use these terms consistently.

No synonyms. No alternatives. No drift.

---

## Core Identity

### User

An individual person who can authenticate into Sentinel using OAuth
(Google or a supported source control provider).

A User may own or belong to one or more Workspaces.

---

### Workspace

The primary tenant boundary in Sentinel.

A Workspace represents an organization, company, or logical account.
All data in Sentinel is scoped to a Workspace.

A Workspace:

-   has exactly one Team
-   owns integrations, repositories, runs, findings, and billing
-   enforces plan limits and usage rules

---

### Team

The membership container within a Workspace.

Each Workspace has exactly one Team.
A Team defines which Users have access to the Workspace.

A Team:

-   contains Members
-   is managed by the Workspace owner
-   is used for access control, not product isolation

---

### Member

A User who belongs to a Team within a Workspace.

Members may have different roles (e.g. Owner, Admin, Member),
which define their permissions within the Workspace.

---

### Invitation

A pending request for a User to join a Team.

An Invitation:

-   is issued by an authorized Member
-   is associated with a Workspace and Team
-   becomes a Member once accepted

---

## Integrations & Source Control

### Provider

An external source control or service platform supported by Sentinel.

Examples:

-   GitHub
-   GitLab (future)

Providers define how Sentinel connects to repositories and receives events.

---

### Connection

A logical link between a Workspace and a Provider.

A Connection represents the authorization state and configuration required
for Sentinel to interact with a Provider on behalf of a Workspace.

---

### Installation

An instance of Sentinel being installed within a Provider account
(e.g. organization or user account).

An Installation:

-   belongs to exactly one Workspace
-   grants Sentinel access to selected repositories
-   is subject to plan limits

---

### Repository

A source code repository connected to Sentinel via a Provider.

A Repository:

-   belongs to a Workspace
-   may be enabled or disabled for reviews
-   has configurable review settings
-   produces Runs and Findings

---

### Repository Settings

Configuration that controls how Sentinel behaves for a specific Repository.

Repository Settings may define:

-   review thresholds
-   enabled rules
-   ignored paths
-   comment limits
-   manual vs automatic review behavior

Settings may be defined via:

-   dashboard configuration
-   configuration file (e.g. `sentinel.yaml`), if present

---

## Review System

### Run

A single execution of Sentinel’s review process.

A Run is created when Sentinel analyzes a change, such as a pull request.
Runs are append-only and immutable once completed.

A Run includes:

-   metadata about the change
-   AI review results
-   execution metrics
-   a policy snapshot

---

### Finding

A discrete issue, observation, or recommendation identified during a Run.

A Finding:

-   has a severity and category
-   may reference a file and line range
-   may include a suggested fix
-   may or may not be surfaced as a comment

Findings are the primary unit of insight in Sentinel.

---

### Annotation

A representation of a Finding surfaced back to the source control platform.

Annotations may appear as:

-   inline comments
-   check summaries
-   status indicators

Not all Findings result in Annotations.

---

## Configuration & Policy

### Policy

A collection of rules and thresholds that govern how Sentinel reviews code.

A Policy defines:

-   which checks are enabled
-   severity thresholds
-   comment limits
-   enforcement behavior

Policies are versioned and captured per Run.

---

### Policy Snapshot

A read-only record of the Policy configuration used during a specific Run.

Policy Snapshots ensure auditability and reproducibility of review behavior.

---

## Usage & Billing

### Plan

A subscription tier that defines what a Workspace is allowed to use.

A Plan controls:

-   number of repositories
-   number of installations
-   feature access
-   usage enforcement rules

Plans do not include AI usage costs by default.

---

### Subscription

The active billing state of a Workspace.

A Subscription associates a Workspace with a Plan
and determines whether Sentinel functionality is enabled.

---

### Usage Record

A metered record of resource consumption produced by a Run.

Usage Records may track:

-   executions
-   duration
-   token estimates
-   cost attribution (if applicable)

---

### Provider Key

A Bring-Your-Own-Key (BYOK) credential supplied by a Workspace
for an external AI provider.

Provider Keys:

-   are stored securely
-   are scoped to a Workspace
-   determine which AI providers are eligible during routing

Sentinel will never route to a provider without a configured Provider Key.

---

## AI & Review Output

### Review Result

The structured output produced by Sentinel’s AI review engine.

A Review Result includes:

-   a summary
-   a collection of Findings
-   execution metrics
-   a policy snapshot

Review Results are stored and used for analytics and dashboards.

---

## General Principles

-   All data access is Workspace-scoped
-   Naming is intentional and stable
-   Terms defined here must not be redefined elsewhere
-   New domain terms must be added here first

This glossary is authoritative.
