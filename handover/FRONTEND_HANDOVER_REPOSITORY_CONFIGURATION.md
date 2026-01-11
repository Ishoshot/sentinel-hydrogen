# Frontend Implementation: Repository Configuration (Phase 5)

> **This is a handover document for the frontend agent.**

---

## Breaking Change: `review_rules` Deprecated

**Action Required:** Remove all frontend support for `review_rules`.

### What Changed

The `review_rules` field has been **removed from the API**. Previously, review policy could be configured two ways:

1. `review_rules` (database, UI-editable) - **REMOVED**
2. `sentinel_config` (from `.sentinel/config.yaml`) - **This is now the only way**

### Why

- `sentinel_config` is more powerful and covers all the same settings
- Source-control-native configuration aligns with Sentinel's philosophy
- Having two systems was confusing and created merge order complexity

### Frontend Changes Required

1. **Remove `review_rules` from TypeScript interfaces** - It's no longer in API responses
2. **Remove any UI for editing review rules** - No forms, modals, or inputs for `review_rules`
3. **Remove any display of `review_rules`** - Don't show it anywhere
4. **Keep `auto_review_enabled`** - This toggle remains (it's an app-level kill switch)

### API Changes

**Before (old):**
```json
{
  "settings": {
    "id": 1,
    "auto_review_enabled": true,
    "review_rules": { "max_findings": 20 },
    "sentinel_config": { ... }
  }
}
```

**After (new):**
```json
{
  "settings": {
    "id": 1,
    "auto_review_enabled": true,
    "sentinel_config": { ... }
  }
}
```

### Update Request Validation

**Before:**
```typescript
// PATCH /api/workspaces/{id}/repositories/{id}
{
  "auto_review_enabled": true,
  "review_rules": { ... }  // REMOVED - will be ignored
}
```

**After:**
```typescript
// PATCH /api/workspaces/{id}/repositories/{id}
{
  "auto_review_enabled": true  // Only this field is accepted
}
```

---

## Context

The backend for Sentinel (Laravel 12 API) has implemented **Repository Configuration** (Phase 5). This feature allows teams to customize Sentinel's behavior per-repository via a `.sentinel/config.yaml` file in their codebase.

This document covers:

-   Displaying Sentinel configuration status for repositories
-   Showing configuration errors when the YAML is invalid
-   Read-only configuration viewer (config is managed in the repo, not via UI)

**Prerequisites**: Phase 2 (GitHub Integration) must be implemented first.

---

## How Repository Configuration Works

### Flow Overview

1. User creates a `.sentinel/config.yaml` file in their repository
2. When repository is synced or a push occurs to the default branch, Sentinel fetches and parses the config
3. If valid, config is stored and applied to all future reviews
4. If invalid (bad YAML syntax or schema violations), an error is stored
5. When a PR is opened with a config error, Sentinel posts a comment explaining the error and skips the review
6. Frontend displays config status and any errors in the repository settings

### Key Concepts

| Term                 | Description                                            |
| -------------------- | ------------------------------------------------------ |
| **Sentinel Config**  | The parsed `.sentinel/config.yaml` from the repository |
| **Config Sync**      | Process of fetching and parsing the config from GitHub |
| **Config Error**     | Error message when config parsing fails                |
| **Config Synced At** | Timestamp of last successful config fetch              |

---

## Updated API Responses

### Repository Settings (Updated)

The `settings` object in repository responses now includes Sentinel config fields:

```
GET /api/workspaces/{workspace_id}/repositories/{repository_id}
Authorization: Bearer {token}

Response 200:
{
  "data": {
    "id": 1,
    "github_id": 123456789,
    "name": "my-repo",
    "full_name": "octocat/my-repo",
    "owner": "octocat",
    "private": false,
    "default_branch": "main",
    "language": "TypeScript",
    "description": "My awesome repository",
    "auto_review_enabled": true,
    "settings": {
      "id": 1,
      "auto_review_enabled": true,
      "review_rules": null,

      // NEW: Sentinel Config Fields
      "sentinel_config": {
        "version": "1.0",
        "triggers": {
          "target_branches": ["main", "develop"],
          "skip_labels": ["skip-review", "wip"],
          "skip_authors": ["dependabot[bot]"]
        },
        "paths": {
          "ignore": ["*.lock", "docs/**"],
          "sensitive": ["**/auth/**"]
        },
        "review": {
          "min_severity": "low",
          "max_findings": 25,
          "categories": {
            "security": true,
            "correctness": true,
            "performance": true,
            "maintainability": true,
            "style": false
          },
          "tone": "constructive",
          "language": "en"
        },
        "guidelines": [
          {
            "path": "docs/CODING_STANDARDS.md",
            "description": "Team coding conventions"
          }
        ],
        "annotations": {
          "style": "review",
          "post_threshold": "medium"
        }
      },
      "config_synced_at": "2025-01-09T00:00:00.000000Z",
      "config_error": null,
      "has_sentinel_config": true,
      "has_config_error": false,

      "updated_at": "2025-01-09T00:00:00.000000Z"
    },
    "installation": { /* installation object */ },
    "created_at": "2025-01-09T00:00:00.000000Z",
    "updated_at": "2025-01-09T00:00:00.000000Z"
  }
}
```

### Configuration States

#### State 1: No Config File (Default Behavior)

```json
{
    "sentinel_config": null,
    "config_synced_at": "2025-01-09T00:00:00.000000Z",
    "config_error": null,
    "has_sentinel_config": false,
    "has_config_error": false
}
```

The repository uses Sentinel's default settings.

#### State 2: Valid Config

```json
{
    "sentinel_config": {
        /* parsed config object */
    },
    "config_synced_at": "2025-01-09T00:00:00.000000Z",
    "config_error": null,
    "has_sentinel_config": true,
    "has_config_error": false
}
```

The repository has a valid custom configuration.

#### State 3: Invalid Config (Error)

```json
{
    "sentinel_config": null,
    "config_synced_at": "2025-01-09T00:00:00.000000Z",
    "config_error": "YAML syntax error: mapping values are not allowed in this context at line 5",
    "has_sentinel_config": false,
    "has_config_error": true
}
```

The config file exists but has errors. Reviews are skipped until fixed.

#### State 4: Never Synced

```json
{
    "sentinel_config": null,
    "config_synced_at": null,
    "config_error": null,
    "has_sentinel_config": false,
    "has_config_error": false
}
```

Config has never been synced (new repository).

---

## Sentinel Config Schema (v1)

This is the full schema that users can define in `.sentinel/config.yaml`:

```yaml
version: "1.0" # Required

triggers:
    target_branches: [main, develop, "release/*"] # Which branches trigger reviews
    skip_source_branches: ["dependabot/*"] # Skip PRs from these branches
    skip_labels: [skip-review, wip] # Skip PRs with these labels
    skip_authors: ["dependabot[bot]"] # Skip PRs by these authors

paths:
    ignore: ["*.lock", "docs/**"] # Files to completely ignore
    include: ["app/**", "src/**"] # Allowlist mode (optional)
    sensitive: ["**/auth/**"] # Extra scrutiny paths

review:
    min_severity: low # Threshold: info|low|medium|high|critical
    max_findings: 25 # Limit per review (0 = unlimited)
    categories: # Enable/disable categories
        security: true
        correctness: true
        performance: true
        maintainability: true
        style: false
    tone: constructive # strict|constructive|educational|minimal
    language: en # Response language (ISO 639-1)
    focus: # Priority areas for this codebase
        - "SQL injection prevention"
        - "Laravel best practices"

guidelines: # Custom team rules
    - path: docs/CODING_STANDARDS.md
      description: "Team coding conventions"
    - path: docs/ARCHITECTURE.md
      description: "System architecture"

annotations:
    style: review # review|comment|check_run
    post_threshold: medium # Min severity to post
    grouped: true # Group findings or individual
    include_suggestions: true # Include code suggestions
```

---

## Frontend Pages/Components Needed

### Repository Settings Page

**Route**: `/workspaces/{slug}/repositories/{id}/settings`

Add a new "Sentinel Configuration" section to the repository settings page.

#### Configuration Display

**State 1: No Configuration (Default)**

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Sentinel Configuration                                                  │
│                                                                          │
│  This repository is using Sentinel's default settings.                   │
│                                                                          │
│  To customize behavior, create a .sentinel/config.yaml file in your     │
│  repository.                                                             │
│                                                                          │
│  [View Documentation]                                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

**State 2: Valid Configuration**

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Sentinel Configuration                              ✓ Config Active     │
│                                                                          │
│  Last synced: 2 hours ago                                               │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Summary                                                        │   │
│  │                                                                  │   │
│  │  • Target branches: main, develop                               │   │
│  │  • Review tone: constructive                                    │   │
│  │  • Min severity: low                                            │   │
│  │  • Max findings: 25                                             │   │
│  │  • Categories: security, correctness, performance               │   │
│  │  • Skip labels: skip-review, wip                                │   │
│  │  • Skip authors: dependabot[bot]                                │   │
│  │  • Guidelines: 2 files                                          │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  [View Full Config]                        [View Documentation]          │
└─────────────────────────────────────────────────────────────────────────┘
```

**State 3: Configuration Error**

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Sentinel Configuration                              ⚠ Config Error      │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  ⚠ Error parsing .sentinel/config.yaml                         │   │
│  │                                                                  │   │
│  │  YAML syntax error: mapping values are not allowed in this      │   │
│  │  context at line 5                                               │   │
│  │                                                                  │   │
│  │  Reviews are skipped until this error is fixed.                 │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  Fix the configuration file in your repository and push to the          │
│  default branch to re-sync.                                             │
│                                                                          │
│  Last sync attempt: 2 hours ago                                         │
│                                                                          │
│  [View Documentation]                                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

### Full Config Viewer Modal

When user clicks "View Full Config", show a modal with the raw config formatted nicely:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  .sentinel/config.yaml                                           [X]   │
│─────────────────────────────────────────────────────────────────────────│
│                                                                          │
│  version: "1.0"                                                         │
│                                                                          │
│  triggers:                                                              │
│    target_branches:                                                     │
│      - main                                                             │
│      - develop                                                          │
│    skip_labels:                                                         │
│      - skip-review                                                      │
│      - wip                                                              │
│                                                                          │
│  review:                                                                │
│    min_severity: low                                                    │
│    max_findings: 25                                                     │
│    tone: constructive                                                   │
│    ...                                                                  │
│                                                                          │
│                                                          [Copy] [Close] │
└─────────────────────────────────────────────────────────────────────────┘
```

### Repository List Enhancement

In the repository list, show config status indicator:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Repositories                                        [Sync from GitHub]  │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  [Lock] octocat/private-repo           TypeScript       ✓ Config │   │
│  │  A private repository                                             │   │
│  │  main • Auto-review: ✓ Enabled                                   │   │
│  │                                                        [Settings] │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  octocat/public-repo                     JavaScript     ⚠ Error  │   │
│  │  A public repository                                             │   │
│  │  main • Auto-review: ✓ Enabled                                   │   │
│  │                                                        [Settings] │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  octocat/another-repo                       Python               │   │
│  │  Another repository (using defaults)                             │   │
│  │  main • Auto-review: ✗ Disabled                                  │   │
│  │                                                        [Settings] │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘

Legend:
  ✓ Config  = Repository has valid .sentinel/config.yaml
  ⚠ Error   = Repository has config with errors
  (nothing) = Repository using default settings
```

---

## State Management Suggestions (Pinia)

Update the existing `Repository` interface to include new settings fields:

```typescript
// stores/github.ts (updated)
interface RepositorySettings {
    id: number;
    auto_review_enabled: boolean;
    review_rules: Record<string, unknown> | null;

    // NEW: Sentinel Config fields
    sentinel_config: SentinelConfig | null;
    config_synced_at: string | null;
    config_error: string | null;
    has_sentinel_config: boolean;
    has_config_error: boolean;

    updated_at: string;
}

interface SentinelConfig {
    version: string;
    triggers?: TriggersConfig;
    paths?: PathsConfig;
    review?: ReviewConfig;
    guidelines?: GuidelineConfig[];
    annotations?: AnnotationsConfig;
}

interface TriggersConfig {
    target_branches?: string[];
    skip_source_branches?: string[];
    skip_labels?: string[];
    skip_authors?: string[];
}

interface PathsConfig {
    ignore?: string[];
    include?: string[];
    sensitive?: string[];
}

interface ReviewConfig {
    min_severity?: "info" | "low" | "medium" | "high" | "critical";
    max_findings?: number;
    categories?: {
        security?: boolean;
        correctness?: boolean;
        performance?: boolean;
        maintainability?: boolean;
        style?: boolean;
    };
    tone?: "strict" | "constructive" | "educational" | "minimal";
    language?: string;
    focus?: string[];
}

interface GuidelineConfig {
    path: string;
    description?: string;
}

interface AnnotationsConfig {
    style?: "review" | "comment" | "check_run";
    post_threshold?: "info" | "low" | "medium" | "high" | "critical";
    grouped?: boolean;
    include_suggestions?: boolean;
}
```

---

## Computed Properties for Display

```typescript
// composables/useSentinelConfig.ts
export const useSentinelConfig = (settings: RepositorySettings | null) => {
    const hasConfig = computed(() => settings?.has_sentinel_config ?? false);
    const hasError = computed(() => settings?.has_config_error ?? false);
    const config = computed(() => settings?.sentinel_config ?? null);
    const error = computed(() => settings?.config_error ?? null);
    const syncedAt = computed(() => settings?.config_synced_at ?? null);

    const configStatus = computed(() => {
        if (hasError.value) return "error";
        if (hasConfig.value) return "active";
        return "default";
    });

    const configStatusLabel = computed(() => {
        switch (configStatus.value) {
            case "error":
                return "Config Error";
            case "active":
                return "Config Active";
            default:
                return "Using Defaults";
        }
    });

    // Summary for display
    const configSummary = computed(() => {
        if (!config.value) return null;

        return {
            targetBranches: config.value.triggers?.target_branches ?? [
                "all branches",
            ],
            tone: config.value.review?.tone ?? "constructive",
            minSeverity: config.value.review?.min_severity ?? "low",
            maxFindings: config.value.review?.max_findings ?? "unlimited",
            enabledCategories: Object.entries(
                config.value.review?.categories ?? {}
            )
                .filter(([, enabled]) => enabled)
                .map(([name]) => name),
            skipLabels: config.value.triggers?.skip_labels ?? [],
            skipAuthors: config.value.triggers?.skip_authors ?? [],
            guidelinesCount: config.value.guidelines?.length ?? 0,
        };
    });

    return {
        hasConfig,
        hasError,
        config,
        error,
        syncedAt,
        configStatus,
        configStatusLabel,
        configSummary,
    };
};
```

---

## Important Implementation Notes

1. **Read-only configuration** - The config is managed in the repository's codebase via `.sentinel/config.yaml`. The frontend only displays the parsed config, it cannot modify it.

2. **Config sync happens automatically** - When repositories are synced from GitHub or when pushes occur to the default branch, the config is re-fetched and parsed. No manual sync button needed.

3. **Error states block reviews** - When `has_config_error` is true, Sentinel skips reviews for that repository. Make this state visually prominent to help users understand why reviews aren't happening.

4. **Relative timestamps** - Display `config_synced_at` as a relative time (e.g., "2 hours ago") for better UX.

5. **YAML display** - When showing the full config, format it as YAML for readability. You can use a YAML library to pretty-print the JSON config object.

6. **Documentation link** - Link to documentation about how to create and configure `.sentinel/config.yaml`. This will be at `docs/SENTINEL_CONFIG.md` in the future.

7. **Handle null settings** - A repository may not have settings if they haven't been created yet. Handle `settings: null` gracefully.

8. **Categories display** - Show only enabled categories in the summary. The default is all enabled except `style`.

9. **Guidelines count** - Show how many guideline files are configured (e.g., "2 files") rather than listing all paths in the summary.

---

## API Client Examples

```typescript
// composables/useRepository.ts (updated)
export const useRepository = (workspaceId: number) => {
    const { $api } = useApi();

    const getRepository = async (repositoryId: number): Promise<Repository> => {
        const response = await $api(
            `/workspaces/${workspaceId}/repositories/${repositoryId}`
        );
        return response.data;
    };

    // Helper to format config for display
    const formatConfigForDisplay = (config: SentinelConfig): string => {
        // Use js-yaml or similar library
        return yaml.dump(config, { indent: 2, lineWidth: 80 });
    };

    return {
        getRepository,
        formatConfigForDisplay,
    };
};
```

---

## Quick Start Checklist

-   [ ] Update `RepositorySettings` TypeScript interface with new fields
-   [ ] Create `useSentinelConfig` composable for config state management
-   [ ] Add config status indicator to repository list items
-   [ ] Create "Sentinel Configuration" section in repository settings page
-   [ ] Handle all three states: default, active, error
-   [ ] Create full config viewer modal
-   [ ] Format config as YAML for display
-   [ ] Add copy-to-clipboard for config
-   [ ] Show relative timestamps for `config_synced_at`
-   [ ] Add documentation link (placeholder for now)
-   [ ] Handle null/missing settings gracefully
-   [ ] Add error state styling (warning colors, icons)

---

## Related Features

### Run Skip Reasons

When a run is skipped due to trigger rules (from config), the run will have:

```json
{
    "status": "skipped",
    "metadata": {
        "skip_reason": "PR label 'wip' matches skip_labels in config"
    }
}
```

Display this in the runs list to help users understand why a review was skipped.

### PR Comments for Config Errors

When a PR is opened on a repository with config errors, Sentinel posts a comment like:

```markdown
## ⚠️ Sentinel Configuration Error

I found an issue with your `.sentinel/config.yaml` file:

> YAML syntax error: mapping values are not allowed in this context at line 5

This review has been skipped. Please fix the configuration and push again.

[View Configuration Documentation](...)
```

The frontend doesn't need to handle this directly, but it's good context for understanding the user experience.

---

Good luck! The backend is ready and tested.
