# Review Flow State Machine

This document defines the complete state machine for the code review system in Sentinel. It maps all states, transitions, events, actions, and data flows from webhook receipt to GitHub annotation posting.

## Overview

The review system is event-driven and follows a clear state machine pattern:
- **Entry Point**: GitHub webhook events (pull_request)
- **Core Entity**: Run (represents a single review execution)
- **State Storage**: `runs.status` enum field
- **Async Processing**: Laravel queues with priority routing
- **Output**: GitHub PR comments and inline annotations

---

## Run States

### 1. `queued` (Initial State)
**Description**: Run has been created and is waiting in the queue for execution

**Entry Conditions**:
- PR webhook received (`opened`, `synchronize`, `reopened`)
- Repository has auto-review enabled
- No config errors detected
- Trigger rules evaluated successfully
- Provider keys available (BYOK)

**Properties**:
- `status`: `queued`
- `started_at`: `null`
- `completed_at`: `null`
- `metrics`: `null`
- `metadata`: Contains PR details, greeting comment ID

**Next States**: `in_progress`, `skipped`

---

### 2. `in_progress` (Active State)
**Description**: Review is currently being executed by the review engine

**Entry Conditions**:
- `ExecuteReviewRun` job dequeued and started
- Previous state was `queued`

**Properties**:
- `status`: `in_progress`
- `started_at`: Timestamp when execution began
- `completed_at`: `null`
- `policy_snapshot`: Captured review policy settings
- `metadata`: PR details

**Active Processes**:
- Context building via `ContextEngine`
  - Diff collection
  - File context extraction
  - Semantic analysis
  - Guidelines loading
  - Repository context
  - Review history
- AI review via `ReviewEngine`
  - Prism SDK integration
  - Provider key resolution (BYOK)
  - Token usage tracking

**Next States**: `completed`, `failed`, `skipped`

---

### 3. `completed` (Terminal State)
**Description**: Review executed successfully with findings generated

**Entry Conditions**:
- Review engine completed without exceptions
- Findings stored in database
- Summary generated

**Properties**:
- `status`: `completed`
- `started_at`: Timestamp
- `completed_at`: Timestamp
- `metrics`: 
  ```json
  {
    "duration_ms": 15234,
    "files_changed": 12,
    "lines_added": 245,
    "lines_deleted": 89,
    "tokens_used_estimated": 8456
  }
  ```
- `policy_snapshot`: Review policy used
- `metadata.review_summary`:
  ```json
  {
    "overview": "Markdown summary text...",
    "verdict": "approve|request_changes|comment",
    "risk_level": "low|medium|high|critical",
    "strengths": ["...", "..."],
    "concerns": ["...", "..."],
    "recommendations": ["...", "..."]
  }
  ```

**Related Records**:
- `findings` (0 to N): Issue found during review
  - Severity: critical, high, medium, low, info
  - Category: security, performance, maintainability, etc.
  - Location: file_path, line_start, line_end
  - Metadata: impact, references, code suggestions
- `annotations` (0 to N): GitHub PR comments created from findings

**Triggered Actions**:
- Activity log created (`RunCompleted`)
- `PostRunAnnotations` job dispatched (delayed 5s)

**Next States**: None (terminal)

---

### 4. `failed` (Terminal State)
**Description**: Review execution failed due to an error

**Entry Conditions**:
- Exception thrown during review execution (excluding `NoProviderKeyException`)
- Examples: Timeout, API errors, validation failures

**Properties**:
- `status`: `failed`
- `started_at`: Timestamp
- `completed_at`: Timestamp
- `metadata.review_failure`:
  ```json
  {
    "message": "Connection timeout after 30s",
    "type": "GuzzleHttp\\Exception\\ConnectException"
  }
  ```

**Triggered Actions**:
- Activity log created (`RunFailed`)
- Skip reason comment posted to GitHub PR
- Error type simplified for user display

**Next States**: None (terminal)

---

### 5. `skipped` (Terminal State)
**Description**: Review was not executed due to a valid skip reason

**Entry Conditions**:
1. **No Provider Keys** (`NoProviderKeyException` thrown)
   - Repository has no BYOK keys configured
   - Most common skip reason
2. **Config Error** (detected before job dispatch)
   - Invalid sentinel.json syntax
   - Config validation failure
3. **Trigger Rules** (evaluated before job dispatch)
   - Branch patterns don't match
   - Author excluded
   - Label requirements not met

**Properties**:
- `status`: `skipped`
- `completed_at`: Timestamp
- `metadata.skip_reason`: 
  - `"no_provider_keys"`
  - `"config_error"`
  - `"trigger_rule_<condition>"`
- `metadata.skip_message`: Human-readable explanation

**Triggered Actions**:
- Activity log created (`RunSkipped`)
- Skip reason comment posted (only for provider key issues)
- Config error comment posted (only for config errors)

**Next States**: None (terminal)

---

## State Transition Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        GitHub PR Webhook Event                       │
│                    (opened, synchronize, reopened)                   │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    GitHubWebhookController                           │
│  • Verify signature                                                   │
│  • Parse event type                                                   │
│  • Dispatch ProcessPullRequestWebhook job                            │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────┐
│                  ProcessPullRequestWebhook Job                       │
│  • Find Installation & Repository                                    │
│  • Check auto-review enabled                                         │
│  • Validate config                                                   │
│  • Evaluate trigger rules                                            │
│  • Post greeting comment                                             │
│  • Create run + dispatch ExecuteReviewRun                            │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
                          ┌─────────┐
                          │ queued  │ ◄─────────────┐
                          └────┬────┘               │
                               │                    │
              ┌────────────────┼────────────────┐   │
              │                │                │   │
       Config │         Provider │        Trigger│   │ Retry
       Error  │         Keys     │        Rules  │   │ (if applicable)
              │         Missing  │        Failed │   │
              ▼                  ▼                ▼   │
        ┌──────────┐      ┌──────────┐    ┌──────────┐
        │ skipped  │      │ skipped  │    │ skipped  │
        │(config)  │      │(no keys) │    │(trigger) │
        └──────────┘      └──────────┘    └──────────┘
                                                  │
                          ┌───────────────────────┘
                          │ ExecuteReviewRun Job
                          │
                          ▼
                   ┌──────────────┐
                   │ in_progress  │
                   └──────┬───────┘
                          │
              ┌───────────┼───────────┐
              │           │           │
        Exception   Success    NoProviderKeyException
              │           │           │
              ▼           ▼           ▼
        ┌─────────┐  ┌───────────┐  ┌──────────┐
        │ failed  │  │ completed │  │ skipped  │
        └─────────┘  └─────┬─────┘  └──────────┘
                           │
                           │ (if findings exist)
                           ▼
                   ┌────────────────────┐
                   │PostRunAnnotations  │
                   │Job (delayed 5s)    │
                   └────────────────────┘
                           │
                           ▼
                   GitHub PR Comments
                   (Inline Annotations)
```

---

## Components & Modules

### 1. **Entry Layer**
**Controller**: `GitHubWebhookController`
- Receives webhook POST
- Verifies signature
- Parses event type
- Dispatches to queue

**Service**: `GitHubWebhookService`
- Signature verification
- Event parsing
- Action determination (trigger review vs metadata sync)

---

### 2. **Orchestration Layer**
**Job**: `ProcessPullRequestWebhook`
- Finds Installation & Repository records
- Checks configuration
- Evaluates trigger rules
- Posts greeting comment
- Creates Run record
- Dispatches review job

**Action**: `CreatePullRequestRun`
- Creates Run with status `queued`
- Stores PR metadata
- Associates greeting comment

**Action**: `SyncPullRequestRunMetadata`
- Updates existing run metadata
- Handles: labels, assignees, reviewers, draft status, title, branch changes

---

### 3. **Review Execution Layer**
**Job**: `ExecuteReviewRun`
- Dequeues run
- Delegates to action
- Handles queue priority

**Action**: `ExecuteReviewRun`
- **State Management**: Updates status through lifecycle
- **Context Building**: Uses `ContextEngine` to gather:
  - Diff (changed files)
  - File contents
  - Semantic analysis (functions, classes, calls)
  - Guidelines (team rules)
  - Repository context (README, CONTRIBUTING)
  - Review history
- **Review Execution**: Calls `ReviewEngine` (Prism SDK)
- **Findings Storage**: Stores results in DB
- **Error Handling**: Catches exceptions, transitions to failed/skipped
- **Activity Logging**: Records completion/failure/skip

**Service**: `ReviewEngine` (Interface: `Contracts\ReviewEngine`)
- Implementation: `PrismReviewEngine`
- Uses Prism SDK for AI review
- Provider key resolution (BYOK)
- Token usage tracking

**Service**: `ContextEngine` (Interface: `Contracts\ContextEngineContract`)
- Orchestrates collectors and filters
- Builds `ContextBag` with all review data
- Collectors run in priority order (100 → 0)
- Filters run in order (10 → 100)

---

### 4. **Output Layer**
**Job**: `PostRunAnnotations`
- Dispatched after completed review
- Delayed 5 seconds
- Posts findings as PR comments

**Action**: `PostRunAnnotations`
- Creates GitHub review comments
- Links comments to findings (annotations)
- Handles GitHub API rate limits

**Action**: `PostsGreetingComment`
- Posts immediate feedback
- "⏳ Review in progress..."
- Returns comment ID

**Action**: `PostsSkipReasonComment`
- Posts skip reason explanation
- Different messages per skip reason
- Links to settings when applicable

**Action**: `PostsConfigErrorComment`
- Posts config error details
- Helps user fix configuration

---

## Queue Architecture

### Queue Names
1. **`webhooks`** (High Priority)
   - ProcessPullRequestWebhook
   - Fast validation and dispatching
   - Target: < 500ms

2. **`reviews-priority`** (Premium Workspaces)
   - ExecuteReviewRun for paid tiers
   - Dedicated workers
   - Target: < 30s

3. **`reviews-default`** (Free Workspaces)
   - ExecuteReviewRun for free tier
   - Shared workers
   - Target: < 2min

4. **`annotations`** (Low Priority)
   - PostRunAnnotations
   - Can be delayed
   - Target: < 10s

### Priority Routing
**Service**: `QueueResolver`
- Determines queue based on workspace tier
- Uses `JobContext` for routing decisions

---

## Data Flow

### Run Creation
```php
[
    'workspace_id' => int,
    'repository_id' => int,
    'external_reference' => 'pr-123',
    'status' => RunStatus::Queued,
    'started_at' => null,
    'completed_at' => null,
    'metrics' => null,
    'policy_snapshot' => null,
    'metadata' => [
        'pull_request_number' => 123,
        'pull_request_title' => 'Add new feature',
        'pull_request_body' => 'Description...',
        'base_branch' => 'main',
        'head_branch' => 'feature-branch',
        'head_sha' => 'abc123...',
        'is_draft' => false,
        'author' => ['login' => 'username', 'avatar_url' => '...'],
        'assignees' => [...],
        'reviewers' => [...],
        'labels' => [...],
        'repository_full_name' => 'owner/repo',
        'greeting_comment_id' => 987654,
    ],
]
```

### Context Bag Structure
```php
[
    'files' => [
        ['filename' => 'path/to/file.php', 'patch' => '...', 'status' => 'modified'],
    ],
    'file_contents' => [
        'path/to/file.php' => '<?php ...',
    ],
    'semantics' => [
        'path/to/file.php' => [
            'language' => 'php',
            'functions' => [...],
            'classes' => [...],
            'calls' => [...],
            'imports' => [...],
        ],
    ],
    'guidelines' => [
        ['path' => '.ai/coding-standards.md', 'content' => '...'],
    ],
    'repository_context' => [
        'readme' => '...',
        'contributing' => '...',
    ],
    'review_history' => [
        ['created_at' => '...', 'summary' => '...', 'key_findings' => [...]],
    ],
    'pull_request' => [...],
    'metrics' => [...],
    'sensitive_files' => ['config/database.php', ...],
    'linked_issues' => [...],
    'pr_comments' => [...],
]
```

### Review Result Structure
```php
[
    'summary' => [
        'overview' => 'Markdown text with **bold** and `code`...',
        'verdict' => 'approve|request_changes|comment',
        'risk_level' => 'low|medium|high|critical',
        'strengths' => ['Good test coverage', ...],
        'concerns' => ['Missing error handling', ...],
        'recommendations' => ['Add input validation', ...],
    ],
    'findings' => [
        [
            'severity' => 'high',
            'category' => 'security',
            'title' => 'SQL Injection Risk',
            'description' => 'Markdown description...',
            'file_path' => 'app/Models/User.php',
            'line_start' => 45,
            'line_end' => 47,
            'confidence' => 'high',
            'metadata' => [
                'impact' => 'Markdown text...',
                'current_code' => '<?php ...',
                'replacement_code' => '<?php ...',
                'explanation' => 'Markdown text...',
                'references' => [
                    '[OWASP SQL Injection](https://...)',
                    'CWE-89',
                ],
            ],
        ],
    ],
    'metrics' => [
        'files_changed' => 12,
        'lines_added' => 245,
        'lines_deleted' => 89,
        'tokens_used_estimated' => 8456,
    ],
]
```

---

## Events & Webhooks

### Received Webhook Events
- `pull_request.opened`
- `pull_request.synchronize`
- `pull_request.reopened`
- `pull_request.edited`
- `pull_request.labeled`
- `pull_request.assigned`
- `pull_request.review_requested`
- `pull_request.converted_to_draft`
- `pull_request.ready_for_review`

### Internal Events
- `run.created`
- `run.started`
- `run.completed`
- `run.failed`
- `run.skipped`
- `annotations.posted`

---

## Real-Time Updates (For Frontend UI)

### Polling Strategy
**Endpoint**: `GET /api/workspaces/{workspace}/runs/{run}`

**Polling Intervals**:
- `queued`: Poll every 2s
- `in_progress`: Poll every 3s
- Terminal states: Stop polling

### WebSocket/Broadcasting (Future)
**Channels**:
- `workspace.{id}.runs` - Broadcast run state changes
- `run.{id}` - Broadcast run-specific updates

**Events**:
- `RunStatusChanged`
- `FindingsGenerated`
- `AnnotationsPosted`

---

## Error Handling & Retries

### Retry Strategies
**ExecuteReviewRun**:
- No automatic retries (one-shot)
- User can trigger manual re-review

**PostRunAnnotations**:
- 3 attempts: 0s, 30s, 60s, 120s
- Exponential backoff
- Handles GitHub rate limits

### Error Types
1. **Config Errors** → Skipped (with comment)
2. **No Provider Keys** → Skipped (with comment)
3. **Trigger Rules** → Skipped (silent)
4. **API Timeouts** → Failed (with comment)
5. **Rate Limits** → Failed (retried)
6. **Validation Errors** → Failed (with comment)

---

## Frontend UI Recommendations

### Run List View
Show runs in cards with:
- Status badge (with color coding)
- PR title & number
- Repository name
- Time ago
- Findings count badge
- Progress indicator (for in_progress)

### Run Detail View
Real-time sections:
1. **Header Card**
   - Status with live updates
   - PR metadata
   - Author, branches, labels
   - Timestamp

2. **Progress Indicator** (for in_progress)
   - Animated spinner
   - Estimated time remaining
   - Current phase: "Building context...", "Analyzing code...", "Generating findings..."

3. **Review Summary Card** (completed)
   - Verdict badge
   - Risk level badge
   - Markdown overview
   - Collapsible strengths/concerns/recommendations

4. **Findings Section**
   - Filterable by severity
   - Real-time count badges
   - Expandable items with markdown

5. **Error State** (failed/skipped)
   - Clear error message
   - Action button (configure keys, fix config)
   - Retry button (if applicable)

### Real-Time Features
- **Status transitions**: Smooth animations
- **Progress updates**: Phase indicators
- **Findings appear**: Animate in as generated
- **Polling management**: Auto-stop on terminal states
- **Optimistic updates**: Instant feedback

### Design Patterns (Like Image)
- **Card-based layout**: Each component in a card
- **Contextual menus**: Three-dot menus for actions
- **Status indicators**: Colored dots for enabled/active/verified
- **Hierarchical grouping**: Related items nested
- **Hover interactions**: Subtle elevation changes
- **Icon usage**: Consistent icon set for actions

---

## Activity Logging

All state transitions log activities:
- `RunCompleted` - Success with findings count
- `RunFailed` - Error with exception details
- `RunSkipped` - Skip reason

Activities are displayed in workspace activity feed.

---

## Summary

This state machine provides:
✅ **Clear state definitions** with entry/exit conditions
✅ **Explicit transitions** between states
✅ **Comprehensive data structures** for all entities
✅ **Component responsibilities** clearly defined
✅ **Queue architecture** for async processing
✅ **Error handling patterns** for each failure mode
✅ **Real-time polling strategy** for frontend
✅ **UI/UX recommendations** for responsive design

Use this as the authoritative reference for implementing real-time run tracking and visualization in the frontend.
