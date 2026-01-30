# Workflow Automation

This document describes the Workflow Automation feature for Sentinel, enabling automated enforcement of PR workflow rules.

## Table of Contents

- [Overview](#overview)
- [Feature: Require Linked Issue](#feature-require-linked-issue)
- [Architecture](#architecture)
- [Configuration](#configuration)
- [Database Schema](#database-schema)
- [Implementation Plan](#implementation-plan)
- [Edge Cases & Reliability](#edge-cases--reliability)
- [Quota & Billing](#quota--billing)

---

## Overview

Workflow Automation extends Sentinel beyond code review to enforce team workflow policies on pull requests. These are configurable rules that trigger automated actions when certain conditions are met.

### Design Principles

1. **Configurable** - All rules are opt-in and customizable via `sentinel.yaml`
2. **Reliable** - Enterprise-grade execution with database persistence
3. **Non-blocking** - Rules execute asynchronously, never blocking PR creation
4. **Auditable** - All actions are logged and trackable
5. **Quota-aware** - Actions count against workspace command limits

### Supported Workflow Rules

| Rule | Description | Status |
|------|-------------|--------|
| `require_linked_issue` | Create and link an issue if PR has none after delay | Planned |

---

## Feature: Require Linked Issue

### Problem Statement

Teams want to ensure every PR is associated with an issue for traceability, project management, and compliance. Currently, developers may forget to link issues, leading to PRs without context.

### Solution

When a PR is opened without a linked issue, Sentinel waits a configurable period (1-15 minutes), then:

1. Checks if an issue has been linked
2. If not, creates an issue automatically
3. Links the issue to the PR
4. Posts a comment notifying the author

### User Flow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              USER EXPERIENCE FLOW                                        │
└─────────────────────────────────────────────────────────────────────────────────────────┘

  Developer opens PR without linking an issue
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  PR is created normally - no blocking                               │
  │  Sentinel schedules a check for N minutes later                     │
  └─────────────────────────────────────────────────────────────────────┘
      │
      │  Developer has N minutes to link an issue manually
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  After N minutes, Sentinel checks for linked issues                 │
  │                                                                     │
  │  ├─ If issue linked: No action taken (check marked complete)        │
  │  │                                                                  │
  │  └─ If no issue linked:                                             │
  │     ├─ Create issue with AI-generated title/body (optional)         │
  │     ├─ Link issue to PR via "Closes #X" or development link         │
  │     └─ Post comment on PR explaining what happened                  │
  └─────────────────────────────────────────────────────────────────────┘
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  GitHub PR now shows linked issue                                   │
  │  Issue contains PR context and link back                            │
  └─────────────────────────────────────────────────────────────────────┘
```

### Issue Detection

Sentinel detects linked issues via:

1. **PR Body References** - `#123`, `fixes #123`, `closes #123`, `resolves #123`
2. **GitHub Timeline Events** - `connected` events from GitHub's timeline API
3. **Development Links** - GitHub's "Development" section (GraphQL API)

### Generated Issue Content

When `ai_generated: true`:

```markdown
## PR #{pr_number}: {pr_title}

{AI-generated summary of the PR changes}

### Changes

- {list of key changes from diff analysis}

### Files Modified

- `path/to/file1.php`
- `path/to/file2.php`

---

*This issue was automatically created by Sentinel because the PR was opened without a linked issue.*

**Pull Request:** #{pr_number}
```

When `ai_generated: false`:

```markdown
## PR #{pr_number}: {pr_title}

{PR description if available, otherwise "No description provided."}

---

*This issue was automatically created by Sentinel because the PR was opened without a linked issue.*

**Pull Request:** #{pr_number}
```

---

## Architecture

### System Flow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                           WORKFLOW AUTOMATION ARCHITECTURE                               │
└─────────────────────────────────────────────────────────────────────────────────────────┘

  PR Opened Webhook (pull_request.opened)
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  ProcessPullRequestWebhook Job                                      │
  │  ├─ Existing review trigger logic                                   │
  │  └─ NEW: Check if workflow automation enabled                       │
  │         └─ Dispatch CreatePendingWorkflowCheck action               │
  └─────────────────────────────────────────────────────────────────────┘
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  CreatePendingWorkflowCheck Action                                  │
  │  ├─ Load repository sentinel config                                 │
  │  ├─ Check if require_linked_issue enabled                           │
  │  ├─ Check skip conditions (branches, authors)                       │
  │  ├─ Calculate scheduled_at (now + delay_minutes)                    │
  │  └─ Create PendingWorkflowCheck record                              │
  └─────────────────────────────────────────────────────────────────────┘
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  pending_workflow_checks TABLE                                      │
  │  ┌───────────────────────────────────────────────────────────────┐  │
  │  │  status: pending                                              │  │
  │  │  check_type: require_linked_issue                             │  │
  │  │  scheduled_at: 2026-01-29 12:10:00                            │  │
  │  │  context: {pr_number, pr_title, author, ...}                  │  │
  │  └───────────────────────────────────────────────────────────────┘  │
  └─────────────────────────────────────────────────────────────────────┘
      │
      │  Scheduler runs every minute
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  ProcessDueWorkflowChecks (Scheduled Command)                       │
  │  ├─ Query: status=pending AND scheduled_at <= now()                 │
  │  ├─ Lock records to prevent duplicate processing                    │
  │  └─ For each: dispatch ExecuteWorkflowCheckJob                      │
  └─────────────────────────────────────────────────────────────────────┘
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  ExecuteWorkflowCheckJob (Queue: workflows)                         │
  │  ├─ Load PendingWorkflowCheck with workspace, repository            │
  │  ├─ Verify PR still exists and is open                              │
  │  ├─ Check workspace quota (command limit)                           │
  │  ├─ Route to handler by check_type                                  │
  │  └─ Update check status (completed/failed/cancelled)                │
  └─────────────────────────────────────────────────────────────────────┘
      │
      ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │  RequireLinkedIssueHandler                                          │
  │  ├─ Fetch PR from GitHub API                                        │
  │  ├─ Detect linked issues (body, timeline, GraphQL)                  │
  │  │                                                                  │
  │  │  If issue found:                                                 │
  │  │  └─ Mark check completed, no action needed                       │
  │  │                                                                  │
  │  │  If no issue found:                                              │
  │  │  ├─ Generate issue content (AI or template)                      │
  │  │  ├─ Create issue via GitHub API                                  │
  │  │  ├─ Update PR body to link issue (optional)                      │
  │  │  ├─ Post comment on PR                                           │
  │  │  └─ Mark check completed with result                             │
  │  │                                                                  │
  │  └─ Record command usage for billing                                │
  └─────────────────────────────────────────────────────────────────────┘
```

### Why Scheduler Over Delayed Jobs?

| Approach | Pros | Cons |
|----------|------|------|
| `dispatch()->delay()` | Simple, no extra table | Lost on worker restart (sync driver), harder to debug |
| **Scheduler + DB** | Persistent, survives failures, queryable, debuggable | More complex, requires migration |

**Decision:** Use scheduler with database persistence for enterprise-grade reliability.

### Queue Configuration

```php
// New queue for workflow jobs
'workflows' => [
    'connection' => 'redis',
    'queue' => 'workflows',
    'timeout' => 120,
    'tries' => 3,
    'backoff' => [30, 60, 120],
],
```

Priority: Between `commands` and `annotations` (lower priority than reviews).

---

## Configuration

### Sentinel Config Schema

Add to `.sentinel/config.yaml`:

```yaml
version: 1

# ... existing config ...

# Workflow automation rules
workflows:
  # Master switch for all workflow automation
  enabled: true

  # Require linked issue rule
  require_linked_issue:
    enabled: true

    # Time to wait before checking (1-15 minutes)
    # Gives developers time to link manually
    delay_minutes: 10

    # Action to take if no issue found
    # Options: 'create_issue', 'comment_only', 'add_label'
    action: create_issue

    # Issue creation settings (when action: create_issue)
    issue:
      # Use AI to generate title/body from PR content
      ai_generated: true

      # Title template (used when ai_generated: false)
      # Variables: {pr_number}, {pr_title}, {pr_author}
      title_template: "PR #{pr_number}: {pr_title}"

      # Labels to add to created issue
      labels:
        - "auto-created"
        - "needs-triage"

      # Assignees for created issue
      # Use 'pr_author' for dynamic assignment
      assignees:
        - pr_author

    # Skip for certain source branches (glob patterns)
    skip_branches:
      - "dependabot/**"
      - "renovate/**"

    # Skip for certain PR authors
    skip_authors:
      - "dependabot[bot]"
      - "renovate[bot]"
```

### Configuration Defaults

```php
// config/sentinel.php

'workflows' => [
    'enabled' => false,

    'require_linked_issue' => [
        'enabled' => false,
        'delay_minutes' => 10,
        'min_delay_minutes' => 1,
        'max_delay_minutes' => 15,
        'action' => 'create_issue',
        'issue' => [
            'ai_generated' => true,
            'title_template' => 'PR #{pr_number}: {pr_title}',
            'labels' => [],
            'assignees' => [],
        ],
        'skip_branches' => [],
        'skip_authors' => [],
    ],
],
```

### Config DTOs

```php
// app/DataTransferObjects/SentinelConfig/WorkflowsConfig.php

final readonly class WorkflowsConfig
{
    public function __construct(
        public bool $enabled = false,
        public ?RequireLinkedIssueConfig $requireLinkedIssue = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            requireLinkedIssue: isset($data['require_linked_issue'])
                ? RequireLinkedIssueConfig::fromArray($data['require_linked_issue'])
                : null,
        );
    }

    public function isRequireLinkedIssueEnabled(): bool
    {
        return $this->enabled
            && $this->requireLinkedIssue !== null
            && $this->requireLinkedIssue->enabled;
    }
}
```

```php
// app/DataTransferObjects/SentinelConfig/RequireLinkedIssueConfig.php

final readonly class RequireLinkedIssueConfig
{
    private const int MIN_DELAY_MINUTES = 1;
    private const int MAX_DELAY_MINUTES = 15;

    public function __construct(
        public bool $enabled = false,
        public int $delayMinutes = 10,
        public string $action = 'create_issue',
        public ?IssueCreationConfig $issue = null,
        public array $skipBranches = [],
        public array $skipAuthors = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            delayMinutes: self::clampDelay((int) ($data['delay_minutes'] ?? 10)),
            action: (string) ($data['action'] ?? 'create_issue'),
            issue: isset($data['issue'])
                ? IssueCreationConfig::fromArray($data['issue'])
                : null,
            skipBranches: (array) ($data['skip_branches'] ?? []),
            skipAuthors: (array) ($data['skip_authors'] ?? []),
        );
    }

    private static function clampDelay(int $minutes): int
    {
        return max(self::MIN_DELAY_MINUTES, min($minutes, self::MAX_DELAY_MINUTES));
    }

    public function getDelayMinutes(): int
    {
        return self::clampDelay($this->delayMinutes);
    }
}
```

```php
// app/DataTransferObjects/SentinelConfig/IssueCreationConfig.php

final readonly class IssueCreationConfig
{
    public function __construct(
        public bool $aiGenerated = true,
        public string $titleTemplate = 'PR #{pr_number}: {pr_title}',
        public array $labels = [],
        public array $assignees = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            aiGenerated: (bool) ($data['ai_generated'] ?? true),
            titleTemplate: (string) ($data['title_template'] ?? 'PR #{pr_number}: {pr_title}'),
            labels: (array) ($data['labels'] ?? []),
            assignees: (array) ($data['assignees'] ?? []),
        );
    }
}
```

---

## Database Schema

### Migration

```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_pending_workflow_checks_table.php

Schema::create('pending_workflow_checks', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignUlid('repository_id')->constrained()->cascadeOnDelete();

    // Check identification
    $table->string('check_type', 50);           // 'require_linked_issue'
    $table->string('external_reference', 255);  // 'github:pr:123'
    $table->unsignedInteger('pull_request_number');

    // Scheduling
    $table->string('status', 20)->default('pending');  // pending, processing, completed, cancelled, failed
    $table->timestamp('scheduled_at');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();

    // Context & Results
    $table->jsonb('config_snapshot')->nullable();  // Config at creation time
    $table->jsonb('context')->nullable();          // PR metadata snapshot
    $table->jsonb('result')->nullable();           // Outcome details

    $table->timestamps();

    // Indexes for scheduler query
    $table->index(['status', 'scheduled_at'], 'idx_pending_scheduled');

    // Indexes for workspace/repository queries
    $table->index(['workspace_id', 'created_at']);
    $table->index(['repository_id', 'pull_request_number']);

    // Prevent duplicate checks for same PR + check type
    $table->unique(
        ['repository_id', 'pull_request_number', 'check_type'],
        'unique_pr_check_type'
    );
});
```

### Model

```php
// app/Models/PendingWorkflowCheck.php

final class PendingWorkflowCheck extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'workspace_id',
        'repository_id',
        'check_type',
        'external_reference',
        'pull_request_number',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'config_snapshot',
        'context',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'check_type' => WorkflowCheckType::class,
            'status' => WorkflowCheckStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'config_snapshot' => 'array',
            'context' => 'array',
            'result' => 'array',
        ];
    }

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WorkflowCheckStatus::Pending);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()->where('scheduled_at', '<=', now());
    }

    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    // Status transitions
    public function markProcessing(): void
    {
        $this->update([
            'status' => WorkflowCheckStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $result = []): void
    {
        $this->update([
            'status' => WorkflowCheckStatus::Completed,
            'completed_at' => now(),
            'result' => $result,
        ]);
    }

    public function markCancelled(string $reason): void
    {
        $this->update([
            'status' => WorkflowCheckStatus::Cancelled,
            'completed_at' => now(),
            'result' => ['cancelled_reason' => $reason],
        ]);
    }

    public function markFailed(string $error, ?array $details = null): void
    {
        $this->update([
            'status' => WorkflowCheckStatus::Failed,
            'completed_at' => now(),
            'result' => [
                'error' => $error,
                'details' => $details,
            ],
        ]);
    }
}
```

### Enums

```php
// app/Enums/WorkflowCheckType.php

enum WorkflowCheckType: string
{
    case RequireLinkedIssue = 'require_linked_issue';

    public function label(): string
    {
        return match ($this) {
            self::RequireLinkedIssue => 'Require Linked Issue',
        };
    }
}
```

```php
// app/Enums/WorkflowCheckStatus.php

enum WorkflowCheckStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::Failed,
        ], true);
    }
}
```

---

## Implementation Plan

### Phase 1: Foundation

| Task | Description | Files |
|------|-------------|-------|
| 1.1 | Create migration | `database/migrations/..._create_pending_workflow_checks_table.php` |
| 1.2 | Create enums | `app/Enums/WorkflowCheckType.php`, `app/Enums/WorkflowCheckStatus.php` |
| 1.3 | Create model | `app/Models/PendingWorkflowCheck.php` |
| 1.4 | Create factory | `database/factories/PendingWorkflowCheckFactory.php` |
| 1.5 | Create config DTOs | `app/DataTransferObjects/SentinelConfig/WorkflowsConfig.php`, etc. |
| 1.6 | Update SentinelConfig | `app/DataTransferObjects/SentinelConfig/SentinelConfig.php` |
| 1.7 | Add config defaults | `config/sentinel.php` |

### Phase 2: Detection & Scheduling

| Task | Description | Files |
|------|-------------|-------|
| 2.1 | Create LinkedIssueDetector service | `app/Services/Workflows/LinkedIssueDetectorService.php` |
| 2.2 | Create CreatePendingWorkflowCheck action | `app/Actions/Workflows/CreatePendingWorkflowCheck.php` |
| 2.3 | Modify PR webhook to trigger check creation | `app/Jobs/GitHub/ProcessPullRequestWebhook.php` |
| 2.4 | Create scheduled command | `app/Console/Commands/ProcessDueWorkflowChecksCommand.php` |
| 2.5 | Register scheduled command | `routes/console.php` |

### Phase 3: Execution

| Task | Description | Files |
|------|-------------|-------|
| 3.1 | Create ExecuteWorkflowCheckJob | `app/Jobs/Workflows/ExecuteWorkflowCheckJob.php` |
| 3.2 | Create ExecuteWorkflowCheck action | `app/Actions/Workflows/ExecuteWorkflowCheck.php` |
| 3.3 | Create RequireLinkedIssueHandler | `app/Services/Workflows/Handlers/RequireLinkedIssueHandler.php` |
| 3.4 | Create CreateIssueForPullRequest action | `app/Actions/Workflows/CreateIssueForPullRequest.php` |
| 3.5 | Add createIssue to GitHubApiService | `app/Services/GitHub/GitHubApiService.php` |
| 3.6 | Integrate quota counting | `app/Actions/Workflows/ExecuteWorkflowCheck.php` |

### Phase 4: AI Enhancement

| Task | Description | Files |
|------|-------------|-------|
| 4.1 | Create IssueContentGenerator service | `app/Services/Workflows/IssueContentGeneratorService.php` |
| 4.2 | Add AI summary generation | Uses existing Prism infrastructure |
| 4.3 | Template variable substitution | For non-AI mode |

### Phase 5: Testing & Documentation

| Task | Description | Files |
|------|-------------|-------|
| 5.1 | Unit tests for DTOs | `tests/Unit/DataTransferObjects/WorkflowsConfigTest.php` |
| 5.2 | Unit tests for services | `tests/Unit/Services/Workflows/` |
| 5.3 | Feature tests for workflow | `tests/Feature/Workflows/RequireLinkedIssueTest.php` |
| 5.4 | Update SENTINEL_CONFIG.md | `docs/SENTINEL_CONFIG.md` |
| 5.5 | Activity logging | Integration with existing activity system |

---

## Edge Cases & Reliability

| Scenario | Handling |
|----------|----------|
| **PR closed before check executes** | Verify PR is open via API; mark check `cancelled` with reason `pr_closed` |
| **PR merged before check executes** | Same as above; reason `pr_merged` |
| **Issue linked after PR but before check** | Re-check at execution time; mark `completed` with `action_taken: none` |
| **PR deleted** | GitHub API returns 404; mark check `cancelled` with reason `pr_not_found` |
| **Duplicate webhook** | Unique constraint prevents duplicate checks; catch exception gracefully |
| **Worker dies mid-execution** | Job retry with idempotency; check status before re-processing |
| **Quota exceeded** | Mark check `failed` with reason `quota_exceeded`; do not create issue |
| **Rate limited by GitHub** | Job retry with exponential backoff (30s, 60s, 120s) |
| **Bot PRs (dependabot)** | Skip via `skip_authors` config |
| **Config changed after PR created** | Use config snapshot from creation time (stored in `config_snapshot`) |
| **Repository disconnected** | Check fails gracefully; `repository_disconnected` |
| **No GitHub installation token** | Check fails gracefully; `installation_not_found` |

### Idempotency

The `RequireLinkedIssueHandler` must be idempotent:

```php
// Before creating issue, verify:
// 1. Check is still in processing state
// 2. No issue was created since check started
// 3. No issue was linked since check started

$existingIssue = $this->linkedIssueDetector->detect($pr);
if ($existingIssue !== null) {
    $check->markCompleted(['action_taken' => 'none', 'reason' => 'issue_already_linked']);
    return;
}
```

---

## Quota & Billing

### Command Limit Integration

Workflow checks count against the workspace's monthly command limit (same as `@sentinel` commands):

```php
// In ExecuteWorkflowCheck action

public function handle(PendingWorkflowCheck $check): void
{
    $workspace = $check->workspace;

    // Check quota before execution
    if (!$this->planLimitEnforcer->canExecuteCommand($workspace)) {
        $check->markFailed('quota_exceeded', [
            'message' => 'Monthly command limit reached',
            'current_usage' => $this->planLimitEnforcer->getCommandUsage($workspace),
            'limit' => $this->planLimitEnforcer->getCommandLimit($workspace),
        ]);
        return;
    }

    // Execute the check...

    // Record usage after successful execution
    $this->planLimitEnforcer->recordCommandUsage($workspace);
}
```

### Why Count Against Commands?

1. **Consistency** - All automated actions have a cost
2. **Predictability** - Users understand their limits
3. **Fairness** - Prevents abuse of automation features
4. **Simplicity** - No new billing dimension needed

### AI Generation Cost

When `ai_generated: true`, the issue content generation uses a small LLM call. This is included in the command count (no separate charge).

---

## Files Summary

### New Files

| File | Purpose |
|------|---------|
| `app/Models/PendingWorkflowCheck.php` | Eloquent model |
| `app/Enums/WorkflowCheckType.php` | Check type enum |
| `app/Enums/WorkflowCheckStatus.php` | Status enum |
| `app/DataTransferObjects/SentinelConfig/WorkflowsConfig.php` | Config DTO |
| `app/DataTransferObjects/SentinelConfig/RequireLinkedIssueConfig.php` | Config DTO |
| `app/DataTransferObjects/SentinelConfig/IssueCreationConfig.php` | Config DTO |
| `app/Actions/Workflows/CreatePendingWorkflowCheck.php` | Create check record |
| `app/Actions/Workflows/ExecuteWorkflowCheck.php` | Execute check logic |
| `app/Actions/Workflows/CreateIssueForPullRequest.php` | Create & link issue |
| `app/Jobs/Workflows/ExecuteWorkflowCheckJob.php` | Queue job |
| `app/Services/Workflows/LinkedIssueDetectorService.php` | Detect linked issues |
| `app/Services/Workflows/IssueContentGeneratorService.php` | AI-generate issue content |
| `app/Services/Workflows/Handlers/RequireLinkedIssueHandler.php` | Handle the check |
| `app/Console/Commands/ProcessDueWorkflowChecksCommand.php` | Scheduled command |
| `database/migrations/..._create_pending_workflow_checks_table.php` | Migration |
| `database/factories/PendingWorkflowCheckFactory.php` | Test factory |
| `tests/Feature/Workflows/RequireLinkedIssueTest.php` | Feature tests |
| `tests/Unit/Services/Workflows/LinkedIssueDetectorServiceTest.php` | Unit tests |

### Modified Files

| File | Change |
|------|--------|
| `app/DataTransferObjects/SentinelConfig/SentinelConfig.php` | Add `workflows` property |
| `app/Jobs/GitHub/ProcessPullRequestWebhook.php` | Trigger workflow check creation |
| `app/Services/GitHub/GitHubApiService.php` | Add `createIssue()` method |
| `app/Services/GitHub/Contracts/GitHubApiService.php` | Add contract method |
| `routes/console.php` | Register scheduled command |
| `config/sentinel.php` | Add workflow defaults |
| `config/horizon.php` | Add workflows queue |
| `docs/SENTINEL_CONFIG.md` | Document `workflows` section |

---

## Future Considerations

### Additional Workflow Rules (Not In Scope)

These could be added later using the same architecture:

| Rule | Description |
|------|-------------|
| `require_labels` | Ensure PRs have at least one label |
| `require_reviewers` | Auto-assign reviewers if none assigned |
| `branch_naming` | Validate branch name format |
| `stale_pr_reminder` | Remind about PRs with no activity |
| `auto_merge` | Merge when checks pass and approved |

### Webhook for Config Changes

Consider triggering check recalculation when:
- PR description is edited (may now contain issue reference)
- PR is linked to issue via GitHub UI

---

_Last updated: 2026-01-29_
