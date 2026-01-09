# Sentinel – UX Principles

This document defines the user experience principles that govern all Sentinel interfaces.
These principles apply to the web application, dashboards, configuration surfaces,
and any future user-facing experiences.

All design and UX decisions MUST conform to these principles.

---

## Design Philosophy

Sentinel follows an **Apple-inspired design philosophy**.

The product should feel:

-   calm
-   precise
-   trustworthy
-   deliberate
-   unobtrusive

Sentinel does not aim to impress with visual flair.
It aims to earn trust through clarity and restraint.

---

## Core Principles

### Clarity Over Density

-   Prefer fewer elements per screen
-   Avoid overcrowded layouts
-   Present information progressively
-   If something does not actively help the user, remove it

Whitespace is a feature, not wasted space.

---

### Signal Over Noise

-   Show only high-confidence, actionable information
-   Avoid excessive alerts, badges, or warnings
-   Limit inline comments and surfaced findings
-   Prefer summaries with drill-down over long lists

Sentinel should feel quiet and intentional.

---

### Neutral First, Color With Purpose

-   The interface is primarily grayscale
-   Color is used to communicate meaning, not decoration
-   A single accent color is used sparingly
-   Semantic colors indicate state or severity only

If removing color breaks comprehension, the design is wrong.

---

### Precision Over Personality

-   Language is concise and professional
-   Avoid playful or conversational copy
-   Avoid jargon and buzzwords
-   Prefer direct statements over marketing language

Sentinel speaks clearly and confidently.

---

### Familiar, Not Novel

-   Use familiar UI patterns
-   Avoid surprising interactions
-   Prefer predictable behavior over clever shortcuts

The interface should feel immediately understandable.

---

## Interaction Principles

### Calm Motion

-   Animations are subtle and purposeful
-   Motion communicates state changes, not decoration
-   Avoid excessive transitions or effects

Motion should never draw attention to itself.

---

### Clear Feedback

-   Every user action produces a visible result
-   Loading, success, and failure states are explicit
-   Errors explain what happened and what to do next

Silence is not acceptable feedback.

---

### Graceful Degradation

-   When an action cannot be completed, explain why
-   When limits are reached, communicate clearly
-   When reviews are skipped, record and surface the reason

Sentinel never fails silently.

---

## Configuration Experience

### Dashboard-First Configuration

-   The primary configuration surface is the Sentinel dashboard
-   Settings are grouped logically and clearly labeled
-   Defaults are sensible and conservative

---

### Configuration as Code (Optional)

-   Repository-level configuration files may override dashboard settings
-   Configuration precedence is predictable and documented
-   Conflicts are surfaced clearly to the user

---

## Review Experience

### Review Output

-   Reviews focus on correctness, security, and maintainability
-   Findings are structured and explainable
-   Confidence matters more than coverage

---

### Inline Feedback

-   Inline comments are limited and intentional
-   Not every finding becomes a comment
-   High-severity issues are prioritized

---

### Manual Triggers

-   Manual review triggers are explicit and predictable
-   Manual actions feel deliberate, not reactive

---

## Dashboard Experience

### Readability First

-   Charts prioritize clarity over complexity
-   Tables are scannable and well-spaced
-   Metrics are contextualized, not isolated

---

### Trend Awareness

-   Emphasize trends over raw counts
-   Highlight changes over time
-   Avoid overwhelming users with historical data

---

## Language & Copy

### Tone

-   Calm
-   Professional
-   Direct
-   Respectful of the user’s time

---

### Word Choices

-   Avoid anthropomorphism
-   Avoid exaggerated claims
-   Avoid unnecessary technical verbosity

Sentinel explains, it does not persuade.

---

## Accessibility & Inclusivity

-   Maintain sufficient color contrast
-   Avoid relying on color alone to convey meaning
-   Ensure keyboard navigability where applicable
-   Text is legible at standard sizes

Accessibility is a baseline requirement.

---

## Platform Neutrality

-   Sentinel UX must not assume a specific source control platform
-   Platform-specific terminology is isolated to integration surfaces
-   Core product language remains platform-agnostic

---

## Guiding Statement

If a design decision conflicts with these principles,
the decision must be reconsidered.

Sentinel values:

-   calm over clever
-   clarity over completeness
-   trust over novelty

---

This document is authoritative.
