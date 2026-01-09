# Sentinel – Product Requirements Document (PRD)

## Overview

Sentinel is a source-control-native code review platform designed to help engineering teams maintain code quality, correctness, and security at scale.

Sentinel integrates directly into existing development workflows, reviews code changes automatically, surfaces high-signal issues, and provides long-term insights into code health — without introducing noise or friction.

The product is built for teams that value clarity, trust, and control.

---

## Problem Statement

Modern engineering teams face increasing pressure to move quickly while maintaining high standards of code quality and reliability.

Existing code review practices suffer from:

-   inconsistent review depth
-   reviewer fatigue
-   missed issues due to time constraints
-   lack of long-term visibility into code quality trends

Many automated tools are either:

-   overly noisy
-   difficult to configure
-   tightly coupled to a single platform
-   opaque in their decision-making

Teams need a solution that is:

-   consistent
-   explainable
-   configurable
-   trusted by engineers

---

## Goals

Sentinel aims to:

1. Provide consistent, high-signal automated code reviews
2. Reduce reviewer fatigue without replacing human judgment
3. Surface meaningful issues early in the development lifecycle
4. Offer clear, explainable feedback
5. Give teams visibility into long-term code quality trends
6. Integrate seamlessly into existing workflows
7. Scale from small teams to large organizations

---

## Non-Goals

Sentinel is **not** intended to:

-   replace human code review
-   automatically merge or block code
-   act as a general coding assistant
-   generate features or large code changes
-   enforce opinionated style rules by default

Sentinel prioritizes **signal over coverage**.

---

## Target Users

### Primary Users

-   Software Engineers
-   Senior Engineers / Tech Leads
-   Engineering Managers

### Secondary Users

-   Platform Engineers
-   Security Engineers
-   DevOps / Infrastructure Teams

---

## Core Value Proposition

Sentinel delivers calm, high-signal code reviews and actionable insights that help teams ship with confidence.

It combines:

-   thoughtful automation
-   configurable policies
-   enterprise-grade architecture
-   long-term analytics

All without disrupting how teams already work.

---

## Key Features (High-Level)

### Automated Code Reviews

-   Reviews code changes automatically when triggered
-   Identifies issues related to correctness, security, reliability, and maintainability
-   Produces structured, explainable findings

---

### Manual Review Triggers

-   Allows users to manually request a review
-   Supports comment-based triggers (e.g. `/review`)
-   Enables targeted reviews when needed

---

### Review Governance

-   Configurable review behavior per workspace and repository
-   Adjustable thresholds and limits
-   Optional configuration-as-code via repository config file

---

### Insightful Feedback

-   High-confidence findings only
-   Clear rationale for each finding
-   Optional suggestions and patches

---

### Dashboards & Analytics

-   Tracks review activity over time
-   Visualizes findings by severity and category
-   Shows repository-level trends
-   Helps teams understand where issues occur most frequently

---

### Enterprise Readiness

-   Multi-tenant architecture
-   Strong isolation between workspaces
-   Auditability of review decisions
-   Bring-Your-Own-Key (BYOK) model for AI providers

---

## Configuration Model

Sentinel supports multiple layers of configuration:

1. Repository configuration file (if present)
2. Repository settings via dashboard
3. Workspace-level defaults
4. System defaults

This precedence order is fixed and predictable.

---

## Billing & Access Model

-   Sentinel uses a subscription model for access and features
-   AI usage is Bring-Your-Own-Key (BYOK) by default
-   Plans define limits such as:
    -   number of repositories
    -   number of installations
    -   feature availability
-   Sentinel does not resell AI usage by default

---

## Platform Strategy

Sentinel is platform-agnostic by design.

-   It integrates natively with supported source control platforms
-   Platform-specific implementations are abstracted behind common interfaces
-   Support for additional platforms may be added over time

---

## MVP Scope (v0.1)

The initial release of Sentinel will include:

-   Workspace and Team management
-   Source control integration (single provider initially)
-   Automated review runs
-   Manual review triggers
-   Review result storage
-   Basic dashboards and metrics
-   Subscription enforcement
-   BYOK provider configuration

---

## Out of Scope for MVP

The following are explicitly out of scope for v0.1:

-   Advanced security scanning
-   Custom rule authoring
-   Multi-team per workspace
-   Real-time collaboration features
-   Managed AI usage billing
-   Multiple source control providers

---

## Success Metrics

Sentinel success will be measured by:

-   adoption and retention
-   review run completion rates
-   user engagement with findings
-   reduction in repeated issues
-   customer trust and satisfaction

---

## Guiding Principles

-   Calm over clever
-   Signal over noise
-   Clarity over automation
-   Control over opacity
-   Trust is earned, not assumed

---

This document defines **what Sentinel is and is not**.
Implementation details are defined in engineering documentation.
