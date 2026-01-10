# Frontend Implementation: Review Execution Pipeline

> **This is a handover document for the frontend agent. Delete this file after use.**

## Context

The backend now executes queued review Runs in the background and persists completed results. Runs transition through `queued → in_progress → completed` (or `failed`) with enriched metrics and summary data.

This handover covers new Run fields the frontend can surface for run detail and lists.

---

## What Changed

- Runs created from GitHub pull request webhooks now dispatch a background execution job.
- Completed Runs include:
  - `metrics` (files changed, lines added/deleted, duration, model/provider placeholders)
  - `policy_snapshot` (resolved from default policy + repository settings)
  - `metadata.review_summary` (summary, risk level, recommendations)
- Findings are created when the review engine returns them (currently empty by default).

---

## API Impact

### Run Detail Response (New Fields)

```
GET /api/workspaces/{workspace_id}/runs/{run_id}
```

The existing response now includes the additional data on completed Runs:

```json
{
  "data": {
    "id": 12,
    "status": "completed",
    "metrics": {
      "files_changed": 1,
      "lines_added": 10,
      "lines_deleted": 2,
      "tokens_used_estimated": 120,
      "model": "test-model",
      "provider": "internal",
      "duration_ms": 420
    },
    "policy_snapshot": {
      "policy_version": 1,
      "enabled_rules": ["summary_only"],
      "severity_thresholds": {"comment": "medium"},
      "comment_limits": {"max_inline_comments": 10},
      "ignored_paths": []
    },
    "metadata": {
      "review_summary": {
        "overview": "Review completed with one finding.",
        "risk_level": "medium",
        "recommendations": ["Address the finding."]
      }
    }
  }
}
```

### Runs List Response

Runs list endpoints are unchanged, but completed Runs now include the same `metrics`, `policy_snapshot`, and `metadata.review_summary` payloads.

---

## Frontend Suggestions

- **Run list**: show status badge + risk level (from `metadata.review_summary.risk_level`) when available.
- **Run detail**: include a compact “Review Summary” card using:
  - `metadata.review_summary.overview`
  - `metadata.review_summary.recommendations`
- **Metrics**: show `files_changed`, `lines_added`, `lines_deleted`, and `duration_ms` as secondary metadata.

---

## UX Notes

- Keep the UI calm and minimal per `docs/product/UX_PRINCIPLES.md`.
- Only surface review summary once status is `completed` or `failed`.
- If `review_summary` is missing, show a neutral “Review pending” placeholder.

---

## Quick Checklist

- [ ] Update Run detail view to render review summary + metrics
- [ ] Show risk level (if present) in runs list
- [ ] Handle missing `review_summary` gracefully

---

Backend is ready for frontend integration.
