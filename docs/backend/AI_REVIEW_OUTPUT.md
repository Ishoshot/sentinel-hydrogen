# Sentinel – AI Review Output

This document defines the canonical structure and rules for AI-generated review output in Sentinel.

All AI providers, prompts, and review engines MUST produce output that conforms
to the structures and constraints defined in this document.

This is a contract, not a suggestion.

---

## Design Goals

The AI review output must be:

-   structured and deterministic
-   explainable and auditable
-   provider-agnostic
-   stable across versions
-   suitable for analytics and dashboards

Human readability is important, but machine consistency is mandatory.

---

## Canonical Object: ReviewResult

Every review execution produces a single **ReviewResult** object.

The ReviewResult is immutable once persisted.

---

## ReviewResult Structure (v1)

### review_result

The top-level object representing the outcome of a review Run.

#### Fields

##### id

A stable, unique identifier for the ReviewResult.

---

##### summary

A high-level overview of the review.

Fields:

-   `overview`  
    A concise, neutral summary of the overall state of the change.

-   `risk_level`  
    One of: `low`, `medium`, `high`, `critical`.

-   `recommendations`  
    A short list of actionable next steps.

---

##### findings

A list of individual findings identified during the review.

Findings may be empty.

---

##### metrics

Execution and contextual metadata.

---

##### policy_snapshot

A read-only snapshot of the policy used for this review.

---

## Finding Object

Each element in `findings` represents a single Finding.

### Required Fields

-   `id`  
    A stable identifier derived from content and context.

-   `severity`  
    One of: `info`, `low`, `medium`, `high`, `critical`.

-   `category`  
    One of:

    -   `security`
    -   `correctness`
    -   `reliability`
    -   `performance`
    -   `maintainability`
    -   `testing`
    -   `style`
    -   `documentation`

-   `title`  
    A short, descriptive summary of the issue.

-   `description`  
    A clear explanation of the issue.

-   `rationale`  
    Why this issue matters.

-   `confidence`  
    A numeric value between `0.0` and `1.0`.

---

### Location Fields (Optional)

-   `file_path`
-   `line_start`
-   `line_end`

If location cannot be determined reliably, these fields must be omitted.

---

### Optional Fields

-   `suggestion`  
    A proposed fix or improvement.

-   `patch`  
    A minimal unified diff snippet.

-   `references`  
    External references (e.g. CWE, OWASP, documentation).

-   `tags`  
    Additional classification labels.

---

## Metrics Object

The `metrics` object provides execution metadata.

Fields:

-   `files_changed`
-   `lines_added`
-   `lines_deleted`
-   `tokens_used_estimated`
-   `model`
-   `provider`
-   `duration_ms`

Metrics are used for reporting and analytics, not enforcement.

---

## Policy Snapshot

The `policy_snapshot` object records the exact policy configuration
used during the review.

Fields may include:

-   `policy_version`
-   `enabled_rules`
-   `severity_thresholds`
-   `comment_limits`
-   `ignored_paths`

Policy snapshots ensure auditability and reproducibility.

---

## Output Constraints

### Determinism

-   The same input and policy should produce equivalent structure
-   Ordering of findings should be consistent

---

### Signal Enforcement

-   Findings must meet confidence thresholds
-   Low-confidence observations should be omitted or downgraded

---

### Comment Eligibility

Not all Findings are eligible for external surfacing.

Eligibility depends on:

-   severity
-   confidence
-   policy thresholds
-   comment limits

---

## Error Handling

If a review cannot be completed:

-   a ReviewResult is still produced
-   `findings` may be empty
-   summary must explain the failure state

Failures must be explicit.

---

## Versioning

-   The ReviewResult schema is versioned
-   Breaking changes require a new major version
-   Runs persist the schema version used

---

## General Rules

-   Output must be valid JSON
-   Fields not defined in this document are forbidden
-   Free-form text must remain concise and neutral
-   No provider-specific language in output

---

This document defines Sentinel’s AI review output contract.
