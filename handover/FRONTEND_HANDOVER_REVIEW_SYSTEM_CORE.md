# Frontend Implementation: Review System Core (Phase 4)

> **This is a handover document for the frontend agent. Delete this file after use.**

## Context

The backend now supports **Review System Core** foundations:
- Runs are created when GitHub pull request webhooks arrive
- Runs are stored with metadata and status
- Findings and annotations are modeled and available in run detail
- API endpoints are available to list runs per repository and fetch a run detail

This handover covers the new **Runs** and **Findings** UI needed in the Nuxt 4 frontend.

---

## Key Concepts

| Term | Description |
|------|-------------|
| **Run** | A single review execution triggered by a pull request event. |
| **Finding** | A structured review issue discovered during a Run. |
| **Annotation** | Feedback surfaced in the source control platform (future use). |

---

## API Endpoints

All endpoints require authentication and workspace access.

```
Authorization: Bearer {token}
```

### List Runs for a Repository

```
GET /api/workspaces/{workspace_id}/repositories/{repository_id}/runs
```

**Query Params**
- `per_page` (optional, default 20, max 100)

**Response 200** (paginated)
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 12,
      "repository_id": 4,
      "external_reference": "github:pull_request:42:abc123",
      "status": "queued",
      "started_at": "2026-01-10T12:00:00.000000Z",
      "completed_at": null,
      "metrics": null,
      "policy_snapshot": null,
      "metadata": {
        "provider": "github",
        "repository_full_name": "org/repo",
        "pull_request_number": 42,
        "pull_request_title": "Improve API response",
        "pull_request_body": "...",
        "base_branch": "main",
        "head_branch": "feature/api",
        "head_sha": "abc123",
        "sender_login": "octocat",
        "action": "opened",
        "installation_id": 12345678
      },
      "repository": null,
      "findings": [],
      "created_at": "2026-01-10T12:00:00.000000Z"
    }
  ],
  "first_page_url": "...",
  "from": 1,
  "last_page": 1,
  "last_page_url": "...",
  "links": ["..."],
  "next_page_url": null,
  "path": "...",
  "per_page": 20,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

---

### Get Run Detail (with Findings)

```
GET /api/workspaces/{workspace_id}/runs/{run_id}
```

**Response 200**
```json
{
  "data": {
    "id": 12,
    "repository_id": 4,
    "external_reference": "github:pull_request:42:abc123",
    "status": "queued",
    "started_at": "2026-01-10T12:00:00.000000Z",
    "completed_at": null,
    "metrics": null,
    "policy_snapshot": null,
    "metadata": { "...": "..." },
    "repository": {
      "id": 4,
      "name": "repo",
      "full_name": "org/repo",
      "owner": "org",
      "private": false,
      "default_branch": "main",
      "language": "TypeScript",
      "description": "...",
      "auto_review_enabled": true,
      "settings": { "...": "..." },
      "installation": null,
      "created_at": "2026-01-10T12:00:00.000000Z",
      "updated_at": "2026-01-10T12:00:00.000000Z"
    },
    "findings": [
      {
        "id": 101,
        "run_id": 12,
        "severity": "high",
        "category": "security",
        "title": "Avoid string interpolation in SQL",
        "description": "...",
        "file_path": "app/Services/QueryBuilder.php",
        "line_start": 42,
        "line_end": 48,
        "confidence": 0.92,
        "metadata": null,
        "annotations": [],
        "created_at": "2026-01-10T12:01:00.000000Z"
      }
    ],
    "created_at": "2026-01-10T12:00:00.000000Z"
  }
}
```

---

## Status Values

Runs currently emit the following statuses:

| Value | Description |
|-------|-------------|
| `queued` | Run has been created and is waiting to be processed |
| `in_progress` | Run is actively reviewing (future pipeline) |
| `completed` | Run finished successfully |
| `failed` | Run failed (future pipeline) |
| `skipped` | Run was skipped (future pipeline) |

---

## Frontend Pages/Components Needed

### Repository Runs List

**Route**: `/workspaces/{slug}/repositories/{id}/runs`

Display recent runs for a repository.

```
┌──────────────────────────────────────────────────────────────────┐
│ Runs for org/repo                                                │
│                                                                  │
│  [Queued] PR #42 • Improve API response                          │
│  Triggered by octocat • 2 minutes ago                            │
│                                                                  │
│  [Completed] PR #39 • Fix linting                                │
│  4 findings • 1 hour ago                                         │
│                                                                  │
│  [Empty State] No runs yet                                       │
└──────────────────────────────────────────────────────────────────┘
```

Suggested fields to show from `metadata`:
- `pull_request_number`
- `pull_request_title`
- `sender_login`
- `head_branch`

### Run Detail View

**Route**: `/workspaces/{slug}/runs/{runId}`

Show run status, metadata, and findings list.

```
┌──────────────────────────────────────────────────────────────────┐
│ Run #12 • PR #42 • queued                                        │
│ org/repo • feature/api → main                                    │
│                                                                  │
│ Findings                                                        │
│ ┌────────────────────────────────────────────────────────────┐   │
│ │ [High] Security — Avoid string interpolation in SQL        │   │
│ │ app/Services/QueryBuilder.php:42-48                        │   │
│ └────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────┘
```

---

## State Management Suggestions (Pinia)

```typescript
interface Run {
  id: number;
  repository_id: number;
  external_reference: string;
  status: 'queued' | 'in_progress' | 'completed' | 'failed' | 'skipped';
  started_at: string | null;
  completed_at: string | null;
  metrics: Record<string, unknown> | null;
  policy_snapshot: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  repository?: Repository;
  findings?: Finding[];
  created_at: string;
}

interface Finding {
  id: number;
  run_id: number;
  severity: string;
  category: string;
  title: string;
  description: string;
  file_path: string | null;
  line_start: number | null;
  line_end: number | null;
  confidence: number | null;
  metadata: Record<string, unknown> | null;
  annotations: Annotation[];
  created_at: string;
}

interface Annotation {
  id: number;
  provider_id: number | null;
  external_id: string | null;
  type: string;
  created_at: string;
}
```

---

## UX Notes

- Keep the UI calm and minimal (align with `docs/product/UX_PRINCIPLES.md`).
- Prioritize clarity: show status and run trigger details above findings.
- Empty states are important: no runs, no findings.
- Use neutral colors and minimal badges (status label only).

---

## Quick Start Checklist

- [ ] Add runs store and API composables
- [ ] Add repository runs list page
- [ ] Add run detail page
- [ ] Render findings list with severity + file location
- [ ] Add empty states
- [ ] Wire navigation from repository → runs list → run detail

---

Backend is ready for frontend integration.
