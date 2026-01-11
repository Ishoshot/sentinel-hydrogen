# Sentinel - Development State & Knowledge Graph

> **Purpose**: This document provides persistent memory across development sessions. Update this document as features are completed.

---

## Product Overview

**Sentinel** is a source-control-native AI-powered code review platform designed to help engineering teams maintain code quality, correctness, and security at scale.

### Core Value Proposition
- Calm, high-signal automated code reviews
- Actionable insights that help teams ship with confidence
- Configurable policies and enterprise-grade architecture
- No noise, no friction - integrates into existing workflows

### Guiding Principles
- Calm over clever
- Signal over noise
- Clarity over automation
- Control over opacity
- Trust is earned, not assumed

---

## Technical Stack

| Component | Technology |
|-----------|------------|
| Backend Language | PHP 8.4 |
| Backend Framework | Laravel 12 |
| Database | PostgreSQL |
| Cache/Queue | Redis |
| AI Routing | PrismPHP |
| Workers | Laravel Queue Workers (Horizon) |
| Testing | Pest 4 |
| Frontend | Vue/Nuxt 4 (separate repository) |
| Auth | Laravel Socialite + Sanctum |
| Code Style | Laravel Pint |

### Architecture Pattern
- **API-only backend** - Frontend is a separate Vue/Nuxt 4 project
- **Multi-tenant** - All data scoped to Workspace
- **Event-driven** - Domain events for decoupling
- **Action pattern** - Controllers delegate to Action classes
- **Domain-driven structure** - Organized by domain, not technical layer

---

## Domain Model

### Core Entities

| Entity | Description |
|--------|-------------|
| **User** | Individual authenticated via OAuth (GitHub/Google) |
| **Workspace** | Primary tenant boundary, owns all data |
| **Team** | Membership container within Workspace (1:1 with Workspace) |
| **TeamMember** | User's membership in a Team with role |
| **Invitation** | Pending request to join a Team |
| **ProviderIdentity** | OAuth provider link to User |
| **Activity** | Workspace activity log entry (audit trail) |

### Source Control Entities (Phase 2 - Implemented)

| Entity | Description |
|--------|-------------|
| **Provider** | External source control platform (GitHub) |
| **Connection** | Authorization between Workspace and Provider |
| **Installation** | GitHub App installed in Provider account |
| **Repository** | Source code repository connected to Sentinel |
| **RepositorySettings** | Configuration for a Repository |

### Review Entities (Phase 4 - Implemented)

| Entity | Description |
|--------|-------------|
| **Run** | Single execution of Sentinel's review process |
| **Finding** | Issue identified during a Run |
| **Annotation** | Finding surfaced in source control platform |

### Future Entities (Not Yet Implemented)

| Entity | Description |
|--------|-------------|
| **Policy** | Collection of rules and thresholds |
| **Plan** | Subscription tier defining limits |
| **Subscription** | Active billing state of Workspace |
| **UsageRecord** | Metered resource consumption |
| **ProviderKey** | BYOK credential for AI provider |

---

## Feature Implementation Roadmap

### Phase 1: Identity & Workspace Foundation
**Status: COMPLETE**

- [x] OAuth login (GitHub + Google)
- [x] User creation & provider identity linking
- [x] Workspace creation on first login
- [x] Single Team per Workspace (auto-created)
- [x] Member invitation & role assignment (Owner, Admin, Member)
- [x] Workspace switcher
- [x] Renaming workspace renames team automatically

### Phase 2: Source Control Integration (GitHub)
**Status: COMPLETE**

- [x] GitHub App configuration (config/github.php)
- [x] Provider model and seeder
- [x] GitHub connection initiation flow
- [x] GitHub App installation callback handling
- [x] Repository discovery and sync
- [x] Webhook handlers (installation, repository events)
- [x] Connection status management (pending, active, disconnected, failed)
- [x] Repository settings management
- [x] Activity logging (workspace audit trail)
- [x] All 135 tests passing (including arch tests)

### Phase 3: Repository Management
**Status: COMPLETE** (merged into Phase 2)

- [x] Repository CRUD operations (via GitHub sync)
- [x] Repository settings (auto_review_enabled, review_rules)
- [x] Default branch configuration (synced from GitHub)
- [ ] Review trigger configuration (deferred to Phase 4)

### Phase 4: Review System Core
**Status: COMPLETE**

- [x] Run creation from webhook events
- [x] Finding and annotation storage (schema + models)
- [x] Run listing and detail APIs
- [x] Review execution pipeline (contract-based architecture)
- [x] Policy resolution (default + repository-specific rules)
- [x] GitHub PR data resolution (files, metrics)
- [x] Finding generation and storage
- [x] AI provider routing (PrismPHP integration)
- [x] Annotation posting to GitHub
- [x] Activity logging for review events

### Phase 5: Repository Configuration
**Status: COMPLETE**

Per-repository configuration via `.sentinel/config.yaml` files.

- [x] **Phase 5A: Core Infrastructure** - COMPLETE
  - [x] Enums: `SentinelConfigSeverity`, `SentinelConfigTone`, `AnnotationStyle`
  - [x] Exceptions: `ConfigParseException`, `ConfigValidationException`
  - [x] DTOs: `SentinelConfig`, `TriggersConfig`, `PathsConfig`, `ReviewConfig`, `CategoriesConfig`, `GuidelineConfig`, `AnnotationsConfig`
  - [x] Services: `SentinelConfigSchema`, `SentinelConfigParser` contract, `SentinelConfigParserService`
  - [x] Migration: Added `sentinel_config`, `config_synced_at`, `config_error` to `repository_settings`
  - [x] Tests: 93 tests covering DTOs, schema validation, parser integration
- [x] **Phase 5B: Config Sync** - COMPLETE
  - [x] `GitHubApiServiceContract` interface for mockable GitHub API service
  - [x] `FetchesSentinelConfig` contract for mockable fetch action
  - [x] `FetchSentinelConfig` action to fetch config from GitHub API
  - [x] `SyncRepositorySentinelConfig` action to parse, validate, and store config
  - [x] Updated `SyncInstallationRepositories` to sync config on repository discovery
  - [x] `ProcessPushWebhook` job to re-sync config when `.sentinel/` files change on default branch
  - [x] Updated `GitHubWebhookController` to handle push webhooks
  - [x] DTO accessor methods on `RepositorySettings` model (`getSentinelConfigDto`, `getConfigOrDefault`, `hasConfigError`)
  - [x] Tests: `FetchSentinelConfigTest`, `SyncRepositorySentinelConfigTest` (107 tests)
- [x] **Phase 5C: Error Handling** - COMPLETE
  - [x] `PostsConfigErrorComment` contract for mockable error comment posting
  - [x] `PostConfigErrorComment` action to post config error comments to PRs
  - [x] `SentinelMessageService::buildConfigErrorComment()` for error message formatting
  - [x] Updated `ProcessPullRequestWebhook` to check `hasConfigError()` and skip reviews
  - [x] Updated `CreatePullRequestRun` to support `skipReason` parameter for skipped runs
  - [x] Skipped runs have `status: Skipped`, `completed_at` set, and `skip_reason` in metadata
  - [x] Activity logging reflects skipped runs with reason
  - [x] Tests: Config error handling in `RunCreationTest`, `PostConfigErrorCommentTest`
- [x] **Phase 5D: Trigger Rules Integration** - COMPLETE
  - [x] `TriggerRuleEvaluator` service for evaluating trigger rules from config
  - [x] Glob pattern matching for branch names (`release/*`, `feature/*`, etc.)
  - [x] Target branch filtering (only review PRs to specified branches)
  - [x] Skip source branches (skip PRs from `dependabot/*`, etc.)
  - [x] Skip labels (skip PRs with `no-review`, `wip` labels)
  - [x] Skip authors (skip PRs from bots like `dependabot[bot]`)
  - [x] Updated `ProcessPullRequestWebhook` to evaluate trigger rules before review
  - [x] Skipped runs created silently (no PR comment) with reason in metadata
  - [x] Tests: `TriggerRuleEvaluatorTest` (18 unit tests), trigger integration tests in `RunCreationTest`
- [x] **Phase 5E: Path Rules Integration** - COMPLETE
  - [x] `ConfiguredPathFilter` context filter for applying repository-specific path rules
  - [x] Ignore patterns support (remove matching files from context)
  - [x] Include patterns support (allowlist mode - keep only matching files)
  - [x] Sensitive patterns support (mark files for extra scrutiny)
  - [x] Glob pattern matching (`*`, `**`, `?` wildcards)
  - [x] Updated `DiffCollector` to store `PathsConfig` in context bag metadata
  - [x] Registered filter in `AppServiceProvider` at order 15 (after VendorPathFilter, before BinaryFileFilter)
  - [x] Metrics recalculation after filtering
  - [x] Tests: `ConfiguredPathFilterTest` (17 tests)
- [x] **Phase 5F: Review Settings Integration** - COMPLETE
  - [x] Updated `ReviewPolicyResolver` to merge `ReviewConfig` from SentinelConfig
  - [x] `min_severity` → `severity_thresholds.comment` mapping
  - [x] `max_findings` → `comment_limits.max_inline_comments` mapping
  - [x] `categories` → `enabled_rules` (enabled categories added to rules)
  - [x] `tone` added to policy for prompt customization
  - [x] `language` added to policy for non-English feedback
  - [x] `focus` added to policy for custom focus areas
  - [x] Updated `resources/views/prompts/review-system.blade.php` with:
    - Feedback tone section (direct, constructive, educational, minimal)
    - Response language section (ISO 639-1 codes)
    - Custom focus areas section
  - [x] Tests: `ReviewPolicyResolverTest` (13 tests)
- [x] **Phase 5G: Guidelines Collector** - COMPLETE
  - [x] `GuidelinesCollector` context collector for fetching team guideline files
  - [x] Fetches `.md`, `.mdx`, `.blade.php` files from GitHub API
  - [x] Max 5 files, max 50KB per file limits enforced
  - [x] Content truncation with friendly message for oversized files
  - [x] Base64 decoding support for GitHub API responses
  - [x] Added guidelines to ContextBag DTO with token estimation
  - [x] Updated `review-user.blade.php` to display Team Guidelines section
  - [x] Registered collector in AppServiceProvider at priority 45
  - [x] Tests: `GuidelinesCollectorTest` (23 tests)
- [x] **Phase 5H: Frontend Display** - COMPLETE
  - [x] Updated `RepositorySettingsResource` with Sentinel config fields
  - [x] Added `sentinel_config`, `config_synced_at`, `config_error` to API response
  - [x] Added `has_sentinel_config`, `has_config_error` boolean helpers
  - [x] Tests: Repository API tests for config display (3 tests)
  - [x] Created frontend handover document: `FRONTEND_HANDOVER_REPOSITORY_CONFIGURATION.md`
- [x] **Phase 5I: Documentation** - COMPLETE
  - [x] Created `.sentinel/config.example.yaml` with all options documented
  - [x] Created `docs/SENTINEL_CONFIG.md` comprehensive user guide
  - [x] Full schema reference with examples
  - [x] Troubleshooting section

### Phase 6: Plans & Billing
**Status: NOT STARTED**

- [ ] Plan model with limits
- [ ] Subscription management
- [ ] Usage metering
- [ ] Limit enforcement
- [ ] BYOK provider key management

### Phase 7: Dashboards & Analytics
**Status: NOT STARTED**

- [ ] Workspace dashboard
- [ ] Repository-level metrics
- [ ] Finding trends
- [ ] Usage reports

---

## Completed Implementation Details

### Identity & Workspace Foundation

#### Database Tables Created
```
users (modified: password nullable, avatar_url added)
workspaces (id, name, slug, owner_id, settings)
teams (id, name, workspace_id)
team_members (id, user_id, team_id, workspace_id, role, joined_at)
invitations (id, email, workspace_id, team_id, invited_by_id, role, token, expires_at, accepted_at)
provider_identities (id, user_id, provider, provider_user_id, email, name, avatar_url, access_token, refresh_token, token_expires_at)
```

#### Enums
- `App\Enums\TeamRole` - Owner, Admin, Member (with permission helpers)
- `App\Enums\OAuthProvider` - GitHub, Google

#### Models
- `App\Models\User` - HasApiTokens trait, workspace relationships
- `App\Models\Workspace` - owner, team, teamMembers, members
- `App\Models\Team` - workspace, members
- `App\Models\TeamMember` - user, team, workspace
- `App\Models\Invitation` - workspace, team, invitedBy
- `App\Models\ProviderIdentity` - user (encrypted tokens)

#### Actions
- `App\Actions\Auth\HandleOAuthCallback`
- `App\Actions\Workspaces\CreateWorkspace`
- `App\Actions\Workspaces\CreateWorkspaceForNewUser`
- `App\Actions\Workspaces\UpdateWorkspace`
- `App\Actions\Workspaces\DeleteWorkspace`
- `App\Actions\Teams\UpdateTeamMemberRole`
- `App\Actions\Teams\RemoveTeamMember`
- `App\Actions\Invitations\CreateInvitation`
- `App\Actions\Invitations\AcceptInvitation`
- `App\Actions\Invitations\CancelInvitation`

#### Controllers
- `App\Http\Controllers\Auth\OAuthController`
- `App\Http\Controllers\WorkspaceController`
- `App\Http\Controllers\TeamMemberController`
- `App\Http\Controllers\InvitationController`

#### API Resources
- `App\Http\Resources\UserResource`
- `App\Http\Resources\WorkspaceResource`
- `App\Http\Resources\TeamResource`
- `App\Http\Resources\TeamMemberResource`
- `App\Http\Resources\InvitationResource`

#### Routes (routes/api.php)
```php
POST /api/invitations/{token}/accept (public, returns 401 with info if unauthenticated)

// Authenticated routes
GET  /api/user
POST /api/logout
GET  /api/workspaces
POST /api/workspaces

// Workspace-scoped routes (middleware: workspace.access)
GET    /api/workspaces/{workspace}
POST   /api/workspaces/{workspace}/switch
PATCH  /api/workspaces/{workspace}
DELETE /api/workspaces/{workspace}
GET    /api/workspaces/{workspace}/members
PATCH  /api/workspaces/{workspace}/members/{member}
DELETE /api/workspaces/{workspace}/members/{member}
GET    /api/workspaces/{workspace}/invitations
POST   /api/workspaces/{workspace}/invitations
DELETE /api/workspaces/{workspace}/invitations/{invitation}
```

#### OAuth Routes (routes/web.php)
```php
GET /auth/{provider}/redirect
GET /auth/{provider}/callback
```

#### Middleware
- `EnsureWorkspaceAccess` - Verifies user belongs to workspace
- `EnsureWorkspaceRole` - Checks required role(s)

#### Policies
- `WorkspacePolicy` - view, update, delete, manageMembers, invite
- `TeamMemberPolicy` - update, delete
- `InvitationPolicy` - create, delete

#### Tests (Phase 1)
- `tests/Feature/Auth/OAuthLoginTest.php` (9 tests)
- `tests/Feature/Workspaces/CreateWorkspaceTest.php` (10 tests)
- `tests/Feature/Workspaces/UpdateWorkspaceTest.php` (8 tests)
- `tests/Feature/Workspaces/WorkspaceSwitcherTest.php` (6 tests)
- `tests/Feature/Members/TeamMemberManagementTest.php` (10 tests)
- `tests/Feature/Invitations/CreateInvitationTest.php` (12 tests)
- `tests/Feature/Invitations/AcceptInvitationTest.php` (9 tests)

### Source Control Integration (GitHub) & Activity Logging

#### Database Tables Created
```
providers (id, type, name, is_active, settings)
connections (id, workspace_id, provider_id, status, external_id, metadata)
installations (id, connection_id, workspace_id, installation_id, account_type, account_login, account_avatar_url, status, permissions, events, suspended_at)
repositories (id, workspace_id, installation_id, github_id, name, full_name, private, default_branch, language, description)
repository_settings (id, repository_id, workspace_id, auto_review_enabled, review_rules)
activities (id, workspace_id, actor_id, type, subject_type, subject_id, description, metadata, created_at)
```

#### Enums
- `App\Enums\ProviderType` - GitHub (extensible)
- `App\Enums\ConnectionStatus` - Pending, Active, Disconnected, Failed
- `App\Enums\InstallationStatus` - Active, Suspended, Deleted
- `App\Enums\GitHubWebhookEvent` - Installation, InstallationRepositories, Push, PullRequest
- `App\Enums\ActivityType` - WorkspaceCreated, WorkspaceUpdated, WorkspaceDeleted, MemberInvited, MemberJoined, MemberRemoved, MemberRoleUpdated, GitHubConnected, GitHubDisconnected, RepositoriesSynced, RepositorySettingsUpdated, RunCreated, RunCompleted, RunFailed, AnnotationsPosted

#### Models
- `App\Models\Provider` - type, name, is_active, settings
- `App\Models\Connection` - workspace, provider, installation, status helpers
- `App\Models\Installation` - connection, workspace, repositories, status helpers
- `App\Models\Repository` - workspace, installation, settings
- `App\Models\RepositorySettings` - repository, workspace
- `App\Models\Activity` - workspace, actor, polymorphic subject

#### Actions
- `App\Actions\GitHub\InitiateGitHubConnection` - Creates pending connection with state
- `App\Actions\GitHub\HandleGitHubInstallation` - Processes installation callback
- `App\Actions\GitHub\SyncInstallationRepositories` - Syncs repos from GitHub API
- `App\Actions\GitHub\UpdateRepositorySettings` - Updates repo settings
- `App\Actions\GitHub\DisconnectGitHubConnection` - Disconnects GitHub
- `App\Actions\Activities\LogActivity` - Creates activity log entries

#### Controllers
- `App\Http\Controllers\GitHub\ConnectionController` - initiate, callback, status, disconnect
- `App\Http\Controllers\GitHub\RepositoryController` - index, show, update, sync
- `App\Http\Controllers\Webhooks\GitHubWebhookController` - handles GitHub webhooks
- `App\Http\Controllers\ActivityController` - lists workspace activities

#### API Resources
- `App\Http\Resources\ProviderResource`
- `App\Http\Resources\ConnectionResource`
- `App\Http\Resources\InstallationResource`
- `App\Http\Resources\RepositoryResource`
- `App\Http\Resources\RepositorySettingsResource`
- `App\Http\Resources\ActivityResource`

#### Services
- `App\Services\GitHub\GitHubAppService` - JWT generation, token management
- `App\Services\GitHub\GitHubApiService` - API calls to GitHub

#### Routes (routes/api.php - GitHub)
```php
// GitHub Integration (workspace-scoped)
GET    /api/workspaces/{workspace}/github/connect      - Initiate connection
GET    /api/workspaces/{workspace}/github/callback     - Installation callback
GET    /api/workspaces/{workspace}/github/status       - Connection status
DELETE /api/workspaces/{workspace}/github/disconnect   - Disconnect GitHub
GET    /api/workspaces/{workspace}/repositories        - List repositories
GET    /api/workspaces/{workspace}/repositories/{repository} - Get repository
PATCH  /api/workspaces/{workspace}/repositories/{repository} - Update settings
POST   /api/workspaces/{workspace}/repositories/sync   - Sync repositories

// Activities (workspace-scoped)
GET    /api/workspaces/{workspace}/activities          - List activities (paginated)

// Webhooks (public)
POST   /api/webhooks/github                            - GitHub webhook handler
```

#### Policies
- `ConnectionPolicy` - view, disconnect
- `RepositoryPolicy` - viewAny, view, update, sync

#### Tests (Phase 2 - 135 total, all passing)
- `tests/Feature/GitHub/ConnectionTest.php`
- `tests/Feature/GitHub/RepositoryTest.php`
- `tests/Feature/Webhooks/GitHubWebhookTest.php`
- `tests/Feature/Activities/ActivityLogTest.php`
- Architecture tests for enums, exceptions, etc.

### Review System Core (Phase 4 - Complete)

#### Database Tables Created
```
runs (id, workspace_id, repository_id, external_reference, status, started_at, completed_at, metrics, policy_snapshot, metadata, created_at)
findings (id, run_id, workspace_id, severity, category, title, description, file_path, line_start, line_end, confidence, metadata, created_at)
annotations (id, finding_id, workspace_id, provider_id, external_id, type, created_at)
```

#### Enums
- `App\Enums\RunStatus` - Queued, InProgress, Completed, Failed, Skipped
- `App\Enums\AnnotationType` - Inline, Summary

#### Models
- `App\Models\Run` - workspace, repository, findings
- `App\Models\Finding` - run, workspace, annotations
- `App\Models\Annotation` - finding, workspace, provider

#### Actions
- `App\Actions\Reviews\CreatePullRequestRun` - Creates/ensures Runs for pull request webhooks (includes activity logging)
- `App\Actions\Reviews\ExecuteReviewRun` - Orchestrates review execution pipeline (includes activity logging + annotation job dispatch)
- `App\Actions\Reviews\PostRunAnnotations` - Posts findings to GitHub as PR review comments

#### Jobs
- `App\Jobs\Reviews\ExecuteReviewRun` - Queued job that dispatches review execution
- `App\Jobs\Reviews\PostRunAnnotations` - Queued job for posting annotations (idempotent, retryable)

#### Services (Contract-Based Architecture)
- `App\Services\Reviews\Contracts\ReviewEngine` - Interface for AI review engines (swap implementations)
- `App\Services\Reviews\Contracts\PullRequestDataResolver` - Interface for fetching PR data
- `App\Services\Reviews\DefaultReviewEngine` - No-op placeholder (returns empty findings, used when no AI key configured)
- `App\Services\Reviews\PrismReviewEngine` - AI-powered reviews using PrismPHP (Anthropic Claude or OpenAI)
- `App\Services\Reviews\ReviewPromptBuilder` - Builds system/user prompts for AI engine
- `App\Services\Reviews\GitHubPullRequestDataResolver` - Fetches PR files from GitHub API
- `App\Services\Reviews\ReviewPolicyResolver` - Merges default policy with repository rules

#### Prompt Templates
- `resources/views/prompts/review-system.blade.php` - AI system prompt (persona, JSON output spec)
- `resources/views/prompts/review-user.blade.php` - AI user prompt (PR details, files)

#### Configuration
- `config/reviews.php` - Default review policy (enabled_rules, severity_thresholds, comment_limits, ignored_paths)
- `config/prism.php` - PrismPHP AI provider configuration (Anthropic, OpenAI, etc.)

#### Environment Variables (AI Integration)
```env
ANTHROPIC_API_KEY=    # Required for Claude AI reviews
OPENAI_API_KEY=       # Alternative: Use OpenAI instead
```

#### Service Binding (AppServiceProvider)
```php
// Conditional binding based on API key availability
$this->app->bind(ReviewEngine::class, function () {
    $anthropicKey = config('prism.providers.anthropic.api_key', '');
    $openAiKey = config('prism.providers.openai.api_key', '');
    if ($anthropicKey !== '' || $openAiKey !== '') {
        return app(PrismReviewEngine::class);
    }
    return app(DefaultReviewEngine::class);
});
```

#### Controllers
- `App\Http\Controllers\RunController` - list runs, show run detail

#### API Resources
- `App\Http\Resources\RunResource`
- `App\Http\Resources\FindingResource`
- `App\Http\Resources\AnnotationResource`

#### Routes (routes/api.php - Runs)
```php
GET /api/workspaces/{workspace}/repositories/{repository}/runs
GET /api/workspaces/{workspace}/runs/{run}
```

#### Policies
- `RunPolicy` - viewAny, view (workspace membership)

#### Activity Types (Review Events)
- `RunCreated` - Review queued for PR
- `RunCompleted` - Review completed with findings
- `RunFailed` - Review failed with error
- `AnnotationsPosted` - Annotations posted to GitHub

#### Tests (Phase 4)
- `tests/Feature/Reviews/RunCreationTest.php` - Run creation from webhooks
- `tests/Feature/Reviews/ExecuteReviewRunTest.php` - Execution pipeline with mocked services
- `tests/Feature/Reviews/PrismReviewEngineTest.php` - AI engine response parsing and normalization
- `tests/Feature/Reviews/PostRunAnnotationsTest.php` - Annotation filtering and posting logic
- `tests/Feature/Reviews/ReviewPolicyResolverTest.php` - Policy resolution with SentinelConfig merge (13 tests)

### Context Engine (Phase 4.5 - Complete)

The Context Engine provides intelligent context gathering for AI reviews. It replaces "blind reviews" (where the AI only saw file names) with rich context including actual code diffs, linked issues, PR discussions, and repository documentation.

#### Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         CONTEXT ENGINE                                   │
│                                                                          │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐              │
│  │  COLLECTORS  │ →  │   FILTERS    │ →  │  ASSEMBLER   │ → Context    │
│  │  (gather)    │    │  (refine)    │    │  (build)     │              │
│  └──────────────┘    └──────────────┘    └──────────────┘              │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Contracts
- `App\Services\Context\Contracts\ContextCollector` - Interface for collectors
- `App\Services\Context\Contracts\ContextFilter` - Interface for filters
- `App\Services\Context\Contracts\ContextEngineContract` - Interface for engine

#### Context Collectors (by priority, highest first)
| Collector | Priority | Source | Data |
|-----------|----------|--------|------|
| `DiffCollector` | 100 | GitHub API | File patches/diffs |
| `LinkedIssueCollector` | 80 | GitHub API | Issues linked via "Fixes #123" patterns |
| `PullRequestCommentCollector` | 70 | GitHub API | PR discussion comments |
| `ReviewHistoryCollector` | 60 | Database | Previous Sentinel reviews on same PR |
| `RepositoryContextCollector` | 50 | GitHub API | README.md, CONTRIBUTING.md |
| `GuidelinesCollector` | 45 | GitHub API | Team guidelines from .sentinel/config.yaml |

#### Context Filters (by order, lowest first)
| Filter | Order | Purpose |
|--------|-------|---------|
| `VendorPathFilter` | 10 | Remove vendor/, node_modules/, etc. |
| `ConfiguredPathFilter` | 15 | Apply repository-specific path rules from .sentinel/config.yaml |
| `BinaryFileFilter` | 20 | Skip binary files, images, fonts |
| `SensitiveDataFilter` | 30 | Redact API keys, secrets, .env files |
| `RelevanceFilter` | 40 | Prioritize important files (app/ > docs/) |
| `TokenLimitFilter` | 100 | Enforce ~80,000 token budget |

#### ContextBag DTO
```php
final class ContextBag {
    public array $pullRequest = [];      // PR metadata
    public array $files = [];            // Files with patches
    public array $metrics = [];          // Change statistics
    public array $linkedIssues = [];     // Referenced issues
    public array $prComments = [];       // PR discussion
    public array $repositoryContext = []; // README, CONTRIBUTING
    public array $reviewHistory = [];    // Previous Sentinel reviews
    public array $guidelines = [];       // Team guidelines from config
    public array $metadata = [];         // Additional data
}
```

#### Token Budget Strategy
```
Total Budget: ~100,000 tokens (Claude 3.5 Sonnet)
Reserved for Response: ~10,000 tokens
Available for Context: ~90,000 tokens

Priority Allocation:
1. System Prompt          ~2,000 tokens
2. PR Metadata            ~500 tokens
3. Code Diffs (HIGH)      ~60,000 tokens  ← Most important
4. Linked Issues (MED)    ~15,000 tokens
5. PR Comments (MED)      ~10,000 tokens
6. Repo Context (LOW)     ~2,500 tokens
```

#### Service Provider Registration
```php
// AppServiceProvider.php
$this->app->singleton(ContextEngine::class, function (): ContextEngine {
    $engine = new ContextEngine();

    // Collectors (highest priority first)
    $engine->registerCollector(app(DiffCollector::class));              // 100
    $engine->registerCollector(app(LinkedIssueCollector::class));       // 80
    $engine->registerCollector(app(PullRequestCommentCollector::class)); // 70
    $engine->registerCollector(app(ReviewHistoryCollector::class));     // 60
    $engine->registerCollector(app(RepositoryContextCollector::class)); // 50
    $engine->registerCollector(app(GuidelinesCollector::class));        // 45

    // Filters (lowest order first)
    $engine->registerFilter(app(VendorPathFilter::class));      // 10
    $engine->registerFilter(app(ConfiguredPathFilter::class));  // 15
    $engine->registerFilter(app(BinaryFileFilter::class));      // 20
    $engine->registerFilter(app(SensitiveDataFilter::class));   // 30
    $engine->registerFilter(app(RelevanceFilter::class));       // 40
    $engine->registerFilter(app(TokenLimitFilter::class));      // 100

    return $engine;
});
```

#### Sensitive Data Patterns Redacted
- API keys (`api_key=xxx`)
- AWS access keys (`AKIA...`)
- GitHub tokens (`ghp_xxx`, `gho_xxx`)
- JWT tokens (`eyJ...`)
- Stripe keys (`sk_live_xxx`, `pk_live_xxx`)
- Private keys (`-----BEGIN RSA PRIVATE KEY-----`)
- Generic secrets (`password=xxx`, `secret=xxx`)
- Entire `.env` family files

#### Tests (Context Engine)
- `tests/Feature/Context/ContextEngineTest.php` - Engine orchestration
- `tests/Feature/Context/DiffCollectorTest.php` - Diff collection
- `tests/Feature/Context/LinkedIssueCollectorTest.php` - Issue parsing
- `tests/Feature/Context/PullRequestCommentCollectorTest.php` - Comment collection
- `tests/Feature/Context/ReviewHistoryCollectorTest.php` - History lookup
- `tests/Feature/Context/RepositoryContextCollectorTest.php` - README/CONTRIBUTING fetch
- `tests/Feature/Context/GuidelinesCollectorTest.php` - Team guidelines fetch (23 tests)
- `tests/Feature/Context/VendorPathFilterTest.php` - Path filtering
- `tests/Feature/Context/ConfiguredPathFilterTest.php` - Repository-specific path rules (17 tests)
- `tests/Feature/Context/BinaryFileFilterTest.php` - Binary detection
- `tests/Feature/Context/SensitiveDataFilterTest.php` - Secret redaction
- `tests/Feature/Context/RelevanceFilterTest.php` - File prioritization
- `tests/Feature/Context/TokenLimitFilterTest.php` - Token budget enforcement

#### Behavior: PR Webhooks & Run Creation

**Trigger Events**: Review runs are created for pull_request webhook events with actions:
- `opened` - New PR opened
- `synchronize` - New commit pushed to existing PR
- `reopened` - Closed PR reopened

**Run Idempotency**: Each run has a unique `external_reference`:
```
github:pull_request:{pr_number}:{head_sha}
```

Since `head_sha` changes with each commit, **each new commit to a PR creates a new Run**. This provides:
- Full audit trail of reviews per commit
- Independent review results for each code state
- Historical record of findings across PR lifetime

**Future Enhancement**: Consider canceling/skipping pending runs when newer commits arrive to avoid reviewing stale code.

### Infrastructure: Queue System (Redis + Horizon)

#### Overview
Sentinel uses Redis-backed queues with Laravel Horizon for queue management and monitoring. The system supports tiered queue priorities and includes a rule-based queue resolution system.

#### Queue Enum (`App\Enums\Queue`)
Central enum defining all valid queue names and their base priorities:

| Queue | Priority | Description |
|-------|----------|-------------|
| `system` | 1 | Critical internal tasks |
| `webhooks` | 5 | External event intake |
| `reviews-enterprise` | 20 | Enterprise tier reviews |
| `reviews-paid` | 30 | Paid tier reviews |
| `reviews-default` | 40 | Free tier reviews |
| `annotations` | 50 | PR comment posting |
| `notifications` | 55 | User notifications |
| `sync` | 70 | Data synchronization |
| `default` | 80 | General workloads |
| `long-running` | 90 | Extended operations |
| `bulk` | 100 | Bulk operations |

Lower priority values = processed first (higher priority).

#### QueueResolver System
Rule-based queue routing for intelligent job placement.

**Components:**
- `App\Services\Queue\JobContext` - Immutable value object carrying dispatch signals (tier, importance, duration, etc.)
- `App\Services\Queue\QueueRuleResult` - Result of rule evaluation (force, boost, penalize, skip)
- `App\Services\Queue\QueueResolution` - Final resolution with trace and scores
- `App\Services\Queue\QueueResolver` - Central service applying rules in priority order
- `App\Services\Queue\Contracts\QueueRule` - Interface for rule implementations

**Rules (in priority order):**
| Rule | Priority | Effect |
|------|----------|--------|
| `SystemJobRule` | 1 | Forces system jobs to `system` queue |
| `WebhookJobRule` | 5 | Forces webhook jobs to `webhooks` queue |
| `ReviewJobTierRule` | 20 | Routes reviews by tier (enterprise/paid/free) |
| `AnnotationJobRule` | 25 | Routes annotation jobs to `annotations` queue |
| `LongRunningJobRule` | 80 | Routes long jobs to `long-running` or `bulk` |

**Usage in Jobs:**
```php
use App\Enums\Queue;

public function __construct(public int $runId, ?Queue $queue = null)
{
    $this->onQueue(($queue ?? Queue::ReviewsDefault)->value);
}
```

#### Horizon Configuration
Four supervisors aligned to workload types:

| Supervisor | Queues | Max Workers | Purpose |
|------------|--------|-------------|---------|
| `supervisor-critical` | system, webhooks | 3 | Critical & time-sensitive |
| `supervisor-reviews` | reviews-enterprise, reviews-paid, reviews-default | 5 | AI review execution |
| `supervisor-default` | annotations, notifications, sync, default | 3 | General workloads |
| `supervisor-background` | long-running, bulk | 2 | Extended operations |

#### Horizon Dashboard Access
Dashboard (`/horizon`) restricted to authorized email in non-production:
```php
Gate::define('viewHorizon', function (?User $user = null): bool {
    return $user !== null && in_array($user->email, ['ishoshot@gmail.com'], true);
});
```

#### Environment Variables
```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Service Provider
`App\Providers\QueueServiceProvider` - Registers QueueResolver as singleton with all rules. Implements `DeferrableProvider` for performance.

#### Tests
- `tests/Feature/Queue/QueueResolverTest.php` - 23 tests covering Queue enum, JobContext, QueueRuleResult, and QueueResolver behavior

---

## Conventions Established

### API Response Format
- Use Laravel API Resources for all responses
- For paginated data: wrap items in resources while keeping Laravel's default pagination structure
```php
$items->setCollection($items->getCollection()->map(fn ($item) => new ItemResource($item)));
return response()->json($items, 200);
```

### Authorization
- Use `Gate::authorize()` in controllers
- Create Policy classes for each model
- Roles: Owner > Admin > Member

### OAuth Flow
1. Frontend redirects to: `{BACKEND_URL}/auth/{provider}/redirect`
2. User authenticates with provider
3. Backend redirects to: `{FRONTEND_URL}/auth/callback?token={sanctum_token}`
4. On error: `{FRONTEND_URL}/auth/error?message={error_message}`

### Environment Configuration
```env
FRONTEND_URL=http://localhost:3000
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

### Testing
- Use Pest framework
- Use `route()` helper with named routes in all tests
- Use factories with states for model creation
- Use `actingAs($user, 'sanctum')` for authenticated requests

### Action Pattern
- All Actions must have their primary method named `handle` (not `execute`)
- Actions use constructor injection for dependencies
- Actions are `final readonly` classes
- See `docs/backend/CODING_STANDARDS.md` for full Action conventions

---

## Frontend Handover Documents
Handover documents for the frontend agent are located in `handover/`:
- `handover/FRONTEND_HANDOVER_IDENTITY_WORKSPACE.md` - Phase 1 (Identity & Workspace)
- `handover/FRONTEND_HANDOVER_GITHUB_INTEGRATION.md` - Phase 2 (GitHub Integration)
- `handover/FRONTEND_HANDOVER_REVIEW_SYSTEM_CORE.md` - Phase 4 (Runs & Findings Data Model)
- `handover/FRONTEND_HANDOVER_REVIEW_EXECUTION_PIPELINE.md` - Phase 4 (Execution Pipeline)
- `handover/FRONTEND_HANDOVER_REPOSITORY_CONFIGURATION.md` - Phase 5 (Repository Configuration)

These documents contain:
- Full API endpoint documentation with request/response examples
- Authentication and OAuth flow details
- Role permissions matrix
- State management suggestions
- UI mockups and component requirements

---

## Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/product/PRD.md` | Product requirements |
| `docs/product/GLOSSARY.md` | Domain vocabulary |
| `docs/product/PLANS_AND_LIMITS.md` | Subscription model |
| `docs/product/UX_PRINCIPLES.md` | UX guidelines |
| `docs/backend/BACKEND_ARCHITECTURE.md` | System architecture |
| `docs/backend/DATA_MODEL.md` | Database design |
| `docs/backend/AUTHENTICATION.md` | Auth implementation |
| `docs/backend/CODING_STANDARDS.md` | Code conventions |
| `docs/backend/TESTING_STRATEGY.md` | Test guidelines |
| `docs/backend/OTHERS.md` | API conventions |
| `docs/SENTINEL_CONFIG.md` | Repository configuration guide |

---

## Next Implementation Steps

1. **Repository Configuration (Phase 5)** ← NEXT

   Enable per-repository configuration via `.sentinel/config.yaml` file. Teams can customize Sentinel's behavior directly in their codebase.

   ### Configuration Schema (v1)
   ```yaml
   version: 1

   triggers:
     target_branches: [main, develop, "release/*"]  # Which branches trigger reviews
     skip_source_branches: ["dependabot/*"]         # Skip PRs from these branches
     skip:                                          # Skip specific combinations
       - from: dev
         to: main
     skip_labels: [skip-review, wip]                # Skip PRs with these labels
     skip_authors: ["dependabot[bot]"]              # Skip PRs by these authors

   paths:
     ignore: ["*.lock", "docs/**"]                  # Files to completely ignore
     include: ["app/**", "src/**"]                  # Allowlist mode (optional)
     sensitive: ["**/auth/**"]                      # Extra scrutiny paths

   review:
     min_severity: low                              # Threshold: info|low|medium|high|critical
     max_findings: 25                               # Limit per review (0 = unlimited)
     categories:                                    # Enable/disable categories
       security: true
       correctness: true
       performance: true
       maintainability: true
       style: false
     tone: constructive                             # strict|constructive|educational|concise
     language: en                                   # Response language (ISO 639-1)
     focus:                                         # Priority areas for this codebase
       - "SQL injection prevention"
       - "Laravel best practices"

   guidelines:                                      # Custom team rules (.md, .mdx, .blade.php)
     - path: docs/CODING_STANDARDS.md
       description: "Team coding conventions"
     - path: docs/ARCHITECTURE.md
       description: "System architecture"

   annotations:
     style: review                                  # review|comment|check_run
     post_threshold: medium                         # Min severity to post
     grouped: true                                  # Group findings or individual
     include_suggestions: true                      # Include code suggestions
   ```

   ### Implementation Tasks

   **Phase A: Core Infrastructure**
   - Create `SentinelConfig` DTO (strongly-typed value object)
   - Create `SentinelConfigSchema` (JSON Schema for validation)
   - Create `SentinelConfigParser` service (YAML → DTO with validation)
   - Migration: Add `sentinel_config`, `config_synced_at`, `config_error` to `repository_settings`

   **Phase B: Config Sync**
   - Create `FetchSentinelConfig` action (fetch from GitHub API)
   - Create `SyncRepositorySentinelConfig` action (parse + validate + store)
   - Hook into repository sync flow
   - Webhook handler: Re-sync on push to default branch
   - Caching strategy for performance

   **Phase C: Error Handling**
   - On invalid YAML: Skip review entirely
   - Post GitHub PR comment explaining the error
   - Create Run with `status: skipped` and error in metadata
   - Surface errors in frontend (read-only config view)

   **Phase D: Trigger Rules Integration**
   - Create `TriggerRuleEvaluator` service
   - Update `CreatePullRequestRun` to check trigger rules
   - Branch pattern matching (wildcards: `release/*`)
   - Skip logic with reason logging

   **Phase E: Path Rules Integration** ✅ COMPLETE
   - Created `ConfiguredPathFilter` context filter
   - Apply ignore patterns to remove files from context
   - Apply include patterns (allowlist mode)
   - Mark sensitive paths for extra handling
   - Glob pattern matching (`*`, `**`, `?`)

   **Phase F: Review Settings Integration** ✅ COMPLETE
   - Updated `ReviewPolicyResolver` to merge with sentinel config
   - Apply `min_severity` threshold
   - Apply `max_findings` limit
   - Apply `categories` filter
   - Apply `tone` to AI prompt (direct, constructive, educational, minimal)
   - Apply `language` for non-English feedback
   - Apply `focus` areas to prompt

   **Phase G: Guidelines Collector** ✅ COMPLETE
   - Created `GuidelinesCollector` context collector
   - Fetches referenced files via GitHub API (GitHubApiServiceContract)
   - Validated file types (.md, .mdx, .blade.php only)
   - Limits enforced: Max 5 files, max 50KB per file
   - Added to ContextBag with token estimation
   - Updated review-user prompt template with Team Guidelines section

   **Phase H: Frontend Display** ✅ COMPLETE
   - Added `sentinel_config`, `config_synced_at`, `config_error` to `RepositorySettingsResource`
   - Added helper booleans: `has_sentinel_config`, `has_config_error`
   - Created comprehensive frontend handover document with UI mockups
   - Tests verify config fields are included in API responses

   **Phase I: Documentation** ✅ COMPLETE
   - Created `.sentinel/config.example.yaml` with all options documented
   - Created `docs/SENTINEL_CONFIG.md` comprehensive user guide
   - Full configuration reference with examples
   - Troubleshooting guide

2. **Plans & Billing (Phase 6)** ← NEXT
   - Create Plan model with limits
   - Implement subscription management
   - Add usage metering
   - Build limit enforcement
   - BYOK provider key management

3. **Dashboards & Analytics (Phase 7)**
   - Workspace dashboard
   - Repository-level metrics
   - Finding trends
   - Usage reports

## Future Enhancements (Backlog)

### Context Display for Frontend
Currently, the Context Engine feeds data to the AI but doesn't expose it to the frontend. Future enhancement:
- Store collected context in Run's metadata field
- Add `context` field to RunResource API response
- Frontend UI to display:
  - Linked issues with their details
  - Previous review history
  - Repository guidelines (README/CONTRIBUTING)
  - PR discussion context

### Additional Context Collectors
- `CIStatusCollector` - Include CI/CD status in context
- `SecurityScanCollector` - Include SAST results
- `DependencyCollector` - Flag dependency updates
- `TestCoverageCollector` - Include coverage reports

### Review Execution Improvements
- Cancel/skip pending runs when newer commits arrive
- Rate limiting for rapid PR updates
- Webhook retry handling

---

## Setup Guides

For setting up OAuth and GitHub App credentials, see:
- `docs/OAUTH_AND_GITHUB_APP_SETUP.md`

---

*Last Updated: 2026-01-11*
*Phases Completed: Identity & Workspace Foundation, Source Control Integration (GitHub), Review System Core, Context Engine, Repository Configuration (Complete)*
*Infrastructure: Queue System (Redis + Horizon)*
*Tests: 456 passing*
*Next Phase: Plans & Billing (Phase 6)*
