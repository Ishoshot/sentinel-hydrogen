# Frontend Implementation: Pull Request Metadata Enhancement

> **This is a handover document for the frontend agent. Delete this file after use.**

## Context

The backend now captures additional pull request metadata from GitHub webhooks:
- **Author** - PR author with avatar
- **Assignees** - Users assigned to the PR
- **Reviewers** - Users requested to review the PR
- **Labels** - PR labels with colors
- **Draft status** - Whether the PR is a draft

This data is now exposed in a structured `pull_request` field on the Run API response.

---

## API Change Summary

### Run Resource Response

The `RunResource` response now includes a new `pull_request` field that structures the PR data from metadata.

**Before (raw metadata):**
```json
{
  "id": 12,
  "metadata": {
    "pull_request_number": 42,
    "pull_request_title": "Add feature",
    "sender_login": "octocat",
    ...
  }
}
```

**After (structured pull_request field):**
```json
{
  "id": 12,
  "pull_request": {
    "number": 42,
    "title": "Add feature",
    "body": "Description here",
    "base_branch": "main",
    "head_branch": "feature/add-feature",
    "head_sha": "abc123def456",
    "is_draft": false,
    "author": {
      "login": "octocat",
      "avatar_url": "https://avatars.githubusercontent.com/u/583231"
    },
    "assignees": [
      {
        "login": "reviewer1",
        "avatar_url": "https://avatars.githubusercontent.com/u/123"
      }
    ],
    "reviewers": [
      {
        "login": "senior-dev",
        "avatar_url": "https://avatars.githubusercontent.com/u/456"
      },
      {
        "login": "tech-lead",
        "avatar_url": "https://avatars.githubusercontent.com/u/789"
      }
    ],
    "labels": [
      {
        "name": "enhancement",
        "color": "84b6eb"
      },
      {
        "name": "needs-review",
        "color": "fbca04"
      }
    ]
  },
  "metrics": null,
  "policy_snapshot": null,
  "repository": {...},
  "findings": [...],
  "created_at": "2026-01-10T12:00:00.000000Z"
}
```

---

## New Fields

### `pull_request` Object

| Field | Type | Description |
|-------|------|-------------|
| `number` | `number` | PR number (e.g., 42) |
| `title` | `string \| null` | PR title |
| `body` | `string \| null` | PR description/body |
| `base_branch` | `string \| null` | Target branch (e.g., "main") |
| `head_branch` | `string \| null` | Source branch (e.g., "feature/x") |
| `head_sha` | `string \| null` | Commit SHA being reviewed |
| `is_draft` | `boolean` | Whether PR is a draft |
| `author` | `User` | PR author |
| `assignees` | `User[]` | Assigned users |
| `reviewers` | `User[]` | Requested reviewers |
| `labels` | `Label[]` | PR labels |

### `User` Object

| Field | Type | Description |
|-------|------|-------------|
| `login` | `string` | GitHub username |
| `avatar_url` | `string \| null` | Avatar URL |

### `Label` Object

| Field | Type | Description |
|-------|------|-------------|
| `name` | `string` | Label name |
| `color` | `string` | Hex color (without #) |

---

## TypeScript Types

Update your types to include:

```typescript
interface User {
  login: string;
  avatar_url: string | null;
}

interface Label {
  name: string;
  color: string;
}

interface PullRequest {
  number: number;
  title: string | null;
  body: string | null;
  base_branch: string | null;
  head_branch: string | null;
  head_sha: string | null;
  is_draft: boolean;
  author: User;
  assignees: User[];
  reviewers: User[];
  labels: Label[];
}

interface Run {
  id: number;
  repository_id: number;
  external_reference: string;
  status: 'queued' | 'in_progress' | 'completed' | 'failed' | 'skipped';
  started_at: string | null;
  completed_at: string | null;
  metrics: Record<string, unknown> | null;
  policy_snapshot: Record<string, unknown> | null;
  pull_request: PullRequest | null;  // NEW
  repository?: Repository;
  findings?: Finding[];
  created_at: string;
}
```

---

## UI Updates

### Run List

Show author avatar and draft badge:

```
┌──────────────────────────────────────────────────────────────────┐
│ Runs for org/repo                                                │
│                                                                  │
│  [Avatar] PR #42 • Add new feature                    [Draft]    │
│  by octocat • feature/add-feature → main                         │
│  Labels: [enhancement] [needs-review]                            │
│                                                                  │
│  [Avatar] PR #39 • Fix linting                      [Completed]  │
│  by contributor • 4 findings • 1 hour ago                        │
└──────────────────────────────────────────────────────────────────┘
```

### Run Detail

Show author, assignees, reviewers, and labels:

```
┌──────────────────────────────────────────────────────────────────┐
│ PR #42 • Add new feature                              [queued]   │
│ org/repo • feature/add-feature → main                            │
│                                                                  │
│ Author: [Avatar] octocat                                         │
│ Assignees: [Avatar] reviewer1                                    │
│ Reviewers: [Avatar] senior-dev, [Avatar] tech-lead              │
│ Labels: [enhancement] [needs-review]                             │
│                                                                  │
│ ─────────────────────────────────────────────────────────────    │
│ Findings (3)                                                     │
│ ┌────────────────────────────────────────────────────────────┐   │
│ │ [High] Security — Avoid string interpolation in SQL        │   │
│ │ app/Services/QueryBuilder.php:42-48                        │   │
│ └────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────┘
```

---

## Label Color Usage

Labels come with hex colors (without `#`). Use them for backgrounds:

```vue
<template>
  <span
    v-for="label in pullRequest.labels"
    :key="label.name"
    :style="{ backgroundColor: `#${label.color}` }"
    class="px-2 py-0.5 rounded text-xs text-white"
  >
    {{ label.name }}
  </span>
</template>
```

For light colors, you may need to calculate text contrast.

---

## Draft PR Handling

Draft PRs should be visually distinguished:
- Show a "Draft" badge
- Consider muted styling for draft runs
- Draft PRs can still be reviewed but are work-in-progress

```vue
<template>
  <span v-if="pullRequest.is_draft" class="badge badge-draft">
    Draft
  </span>
</template>
```

---

## Avatar Component

Reuse a consistent avatar component:

```vue
<template>
  <img
    v-if="user.avatar_url"
    :src="user.avatar_url"
    :alt="user.login"
    class="w-6 h-6 rounded-full"
  />
  <div v-else class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center">
    {{ user.login.charAt(0).toUpperCase() }}
  </div>
</template>
```

---

## Live Metadata Sync

The backend now automatically syncs PR metadata when changes occur:

- **Labels**: Added or removed via GitHub
- **Assignees**: Assigned or unassigned
- **Reviewers**: Review requested or removed
- **Draft status**: Converted to draft or marked ready for review

This means the `pull_request` data stays up-to-date even after the initial run is created. The response includes `last_synced_at` timestamp when metadata has been updated.

---

## Backwards Compatibility

- The `pull_request` field will be `null` for runs created before this update
- Fall back to `metadata.sender_login` for author if `pull_request.author` is missing
- Empty arrays for `assignees`, `reviewers`, `labels` is valid (no assignees, etc.)

---

## Quick Start Checklist

- [ ] Update `Run` TypeScript interface with `pull_request` field
- [ ] Add `User`, `Label`, `PullRequest` interfaces
- [ ] Update run list to show author avatar
- [ ] Add draft badge for draft PRs
- [ ] Add labels display with colors
- [ ] Update run detail to show assignees and reviewers
- [ ] Handle null `pull_request` for backwards compatibility
- [ ] Create reusable `UserAvatar` component
- [ ] Create reusable `LabelBadge` component

---

Backend is ready for frontend integration.
