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

### Phase 5: Plans & Billing
**Status: NOT STARTED**

- [ ] Plan model with limits
- [ ] Subscription management
- [ ] Usage metering
- [ ] Limit enforcement
- [ ] BYOK provider key management

### Phase 6: Dashboards & Analytics
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

---

## Next Implementation Steps

1. **Plans & Billing (Phase 5)**
   - Create Plan model with limits
   - Implement subscription management
   - Add usage metering
   - Build limit enforcement
   - BYOK provider key management

2. **Dashboards & Analytics (Phase 6)**
   - Workspace dashboard
   - Repository-level metrics
   - Finding trends
   - Usage reports

---

## Setup Guides

For setting up OAuth and GitHub App credentials, see:
- `docs/OAUTH_AND_GITHUB_APP_SETUP.md`

---

*Last Updated: 2026-01-10*
*Phases Completed: Identity & Workspace Foundation, Source Control Integration (GitHub), Review System Core*
*Infrastructure: Queue System (Redis + Horizon)*
*Next Phase: Plans & Billing (Phase 5)*
