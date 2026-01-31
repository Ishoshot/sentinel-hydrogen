# GitHub Integration

## Connecting Sentinel to Your Code

Sentinel's GitHub integration is the bridge between your code and AI-powered reviews. It's built on the **GitHub App** architecture, which is the modern, secure way for third-party applications to interact with GitHub.

Think of it like giving Sentinel a VIP pass to your repositories—but a pass that you control, with specific permissions that you approve.

---

## How GitHub Apps Work

### The Big Picture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     GITHUB APP ARCHITECTURE                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                          YOUR GITHUB                                     │   │
│  │                                                                          │   │
│  │   Organization                                                           │   │
│  │   ┌───────────────────────────────────────────────────────────────────┐ │   │
│  │   │  • acme/backend                                                    │ │   │
│  │   │  • acme/frontend                                                   │ │   │
│  │   │  • acme/infrastructure                                             │ │   │
│  │   │                                                                    │ │   │
│  │   │         ┌─────────────────────────────────┐                       │ │   │
│  │   │         │    SENTINEL APP INSTALLED       │                       │ │   │
│  │   │         │    on: acme/backend,            │                       │ │   │
│  │   │         │        acme/frontend            │                       │ │   │
│  │   │         │                                 │                       │ │   │
│  │   │         │    Permissions:                 │                       │ │   │
│  │   │         │    ✓ Read code                  │                       │ │   │
│  │   │         │    ✓ Read PRs                   │                       │ │   │
│  │   │         │    ✓ Write PR comments          │                       │ │   │
│  │   │         │    ✓ Read issues                │                       │ │   │
│  │   │         └─────────────────────────────────┘                       │ │   │
│  │   │                          │                                         │ │   │
│  │   │                          │ Webhooks                                │ │   │
│  │   └──────────────────────────┼────────────────────────────────────────┘ │   │
│  │                              │                                           │   │
│  └──────────────────────────────┼───────────────────────────────────────────┘   │
│                                 │                                                │
│                                 ▼                                                │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                          SENTINEL                                        │   │
│  │                                                                          │   │
│  │   Receives webhooks:                    Can perform:                    │   │
│  │   • PR opened/updated                   • Read file contents            │   │
│  │   • Push events                         • Post review comments          │   │
│  │   • Issue comments                      • Create check runs             │   │
│  │   • Installation changes                • Respond to @sentinel         │   │
│  │                                                                          │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### GitHub App vs. OAuth App

| Feature | GitHub App | OAuth App |
|---------|------------|-----------|
| Permission granularity | Fine-grained | Broad scopes |
| Acts as | Bot account | User's identity |
| Installation scope | Per-org/repo | Per-user |
| Webhook delivery | Built-in | Manual setup |
| Best for | Automation | User actions |

Sentinel uses a **GitHub App** because it needs to:
- Act as an automated reviewer (not as a user)
- Have specific, limited permissions
- Receive webhooks automatically
- Support installation on specific repositories

---

## Connection Flow

### Step 1: Initiate Connection

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     GITHUB CONNECTION FLOW                                       │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. USER CLICKS "CONNECT GITHUB"                                                │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  In Sentinel dashboard:                                                 │    │
│  │  Settings > Integrations > GitHub > [Connect GitHub]                   │    │
│  │                                                                          │    │
│  │  Triggers: InitiateGitHubConnection action                             │    │
│  │  • Generates state token (CSRF protection)                             │    │
│  │  • Stores in session                                                   │    │
│  │  • Redirects to GitHub App installation URL                            │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  2. GITHUB INSTALLATION PAGE                                                    │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  User sees GitHub's native installation UI:                             │    │
│  │                                                                          │    │
│  │  ┌───────────────────────────────────────────────────────────────┐     │    │
│  │  │  Install Sentinel on:                                          │     │    │
│  │  │                                                                 │     │    │
│  │  │  ○ All repositories                                            │     │    │
│  │  │  ● Only select repositories                                    │     │    │
│  │  │    ☑ acme/backend                                              │     │    │
│  │  │    ☑ acme/frontend                                             │     │    │
│  │  │    ☐ acme/infrastructure                                       │     │    │
│  │  │                                                                 │     │    │
│  │  │  [Install & Authorize]                                         │     │    │
│  │  └───────────────────────────────────────────────────────────────┘     │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  3. CALLBACK TO SENTINEL                                                        │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  GitHub redirects to: sentinel.app/github/callback                     │    │
│  │  With: installation_id, setup_action, state                            │    │
│  │                                                                          │    │
│  │  HandleGitHubInstallation action:                                       │    │
│  │  • Verifies state token                                                 │    │
│  │  • Creates/updates Connection record                                    │    │
│  │  • Creates Installation record                                          │    │
│  │  • Fetches repository list from GitHub API                             │    │
│  │  • Creates Repository records                                           │    │
│  │  • Redirects to dashboard with success message                         │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Step 2: Installation Sync

Once installed, Sentinel syncs your repositories:

```php
// SyncInstallationRepositories action
foreach ($githubRepos as $repo) {
    Repository::updateOrCreate(
        [
            'installation_id' => $installation->id,
            'external_id' => $repo['id'],
        ],
        [
            'workspace_id' => $workspace->id,
            'name' => $repo['name'],
            'full_name' => $repo['full_name'],
            'default_branch' => $repo['default_branch'],
            'is_private' => $repo['private'],
            'is_enabled' => false, // Manual enable required
        ]
    );
}
```

---

## Webhook Handling

### Supported Webhook Events

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     WEBHOOK EVENTS                                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  PULL REQUEST EVENTS                                                            │
│  ├─ pull_request.opened          → Triggers auto-review                        │
│  ├─ pull_request.synchronize     → Re-review on new commits                    │
│  ├─ pull_request.reopened        → Review if previously skipped                │
│  ├─ pull_request.edited          → Update PR metadata                          │
│  ├─ pull_request.labeled         → Update labels, check trigger rules          │
│  ├─ pull_request.assigned        → Update assignees                            │
│  ├─ pull_request.review_requested→ Update reviewers                            │
│  ├─ pull_request.converted_to_draft → Skip if draft                            │
│  └─ pull_request.ready_for_review → Review draft PR                            │
│                                                                                  │
│  ISSUE COMMENT EVENTS                                                           │
│  └─ issue_comment.created        → Process @sentinel commands                  │
│                                                                                  │
│  PUSH EVENTS                                                                    │
│  └─ push                         → Sync sentinel config if changed             │
│                                                                                  │
│  INSTALLATION EVENTS                                                            │
│  ├─ installation.created         → New installation                            │
│  ├─ installation.deleted         → Installation removed                        │
│  └─ installation.suspend         → Installation suspended                      │
│                                                                                  │
│  INSTALLATION REPOSITORIES EVENTS                                               │
│  └─ installation_repositories    → Repos added/removed from installation       │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Webhook Processing

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     WEBHOOK PROCESSING FLOW                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. RECEIVE WEBHOOK                                                             │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  POST /api/github/webhook                                               │    │
│  │  Headers:                                                               │    │
│  │    X-GitHub-Event: pull_request                                        │    │
│  │    X-Hub-Signature-256: sha256=abc123...                               │    │
│  │    X-GitHub-Delivery: unique-id                                        │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  2. SIGNATURE VERIFICATION                                                      │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Verify webhook signature using GITHUB_WEBHOOK_SECRET                  │    │
│  │  Prevents malicious fake webhooks                                      │    │
│  │  If invalid → 401 Unauthorized                                         │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  3. RECORD WEBHOOK                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  RecordIncomingWebhook action:                                         │    │
│  │  • Store in incoming_webhooks table                                    │    │
│  │  • Useful for debugging and idempotency                                │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  4. DISPATCH JOB                                                                │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Based on event type:                                                   │    │
│  │  • pull_request → ProcessPullRequestWebhook job                        │    │
│  │  • issue_comment → ProcessIssueCommentWebhook job                      │    │
│  │  • installation → ProcessInstallationWebhook job                       │    │
│  │  • push → ProcessPushWebhook job                                       │    │
│  │                                                                          │    │
│  │  Jobs are queued on high-priority 'webhooks' queue                     │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  5. IMMEDIATE 200 RESPONSE                                                      │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Return 200 OK immediately                                              │    │
│  │  GitHub expects quick responses (< 10s)                                │    │
│  │  Actual processing happens async in job                                │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Idempotency

Webhooks can be delivered multiple times. Sentinel handles this:

```php
// Check for duplicate webhook using delivery ID
$existing = IncomingWebhook::where('delivery_id', $deliveryId)->first();
if ($existing) {
    Log::info('Duplicate webhook, skipping', ['delivery_id' => $deliveryId]);
    return;
}
```

---

## Repository Management

### Enabling Repositories

Repositories are disabled by default. Enable them manually:

```bash
PATCH /api/v1/workspaces/{workspace}/github/repositories/{repository}
{
  "is_enabled": true
}
```

### Repository Settings

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     REPOSITORY SETTINGS                                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  GENERAL                                                                        │
│  ├─ is_enabled          Enable/disable auto-review                             │
│  └─ auto_review         Automatic review on PR events                          │
│                                                                                  │
│  CONFIGURATION SOURCE                                                           │
│  ├─ .sentinel/config.yaml    Repository-level config file                      │
│  └─ Dashboard settings       Fallback when no config file                      │
│                                                                                  │
│  PROVIDER KEYS                                                                  │
│  └─ Uses workspace-level BYOK keys                                             │
│                                                                                  │
│  SENTINEL CONFIG (from file)                                                    │
│  ├─ triggers            Branch/label/author rules                              │
│  ├─ paths               Include/exclude patterns                               │
│  ├─ review              Categories, severity, tone                             │
│  ├─ guidelines          Team documentation files                               │
│  ├─ annotations         Comment posting settings                               │
│  └─ provider            AI provider preferences                                │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Config File Sync

When you push changes to `.sentinel/config.yaml`, Sentinel detects it:

```
Push event received
        │
        ▼
Check if .sentinel/ files changed
        │
        ├─ Yes → Fetch new config from GitHub
        │         Parse and validate
        │         Update repository settings
        │
        └─ No → Do nothing
```

---

## GitHub API Integration

### Authentication

Sentinel uses **installation access tokens** for API calls:

```php
// Get installation token (auto-refreshed)
$token = $this->githubService->getInstallationToken($installation);

// Make API call
$response = Http::withToken($token)
    ->get("https://api.github.com/repos/{$owner}/{$repo}/pulls/{$number}");
```

### API Operations

| Operation | GitHub API Endpoint | Used By |
|-----------|---------------------|---------|
| Get PR details | `GET /repos/{owner}/{repo}/pulls/{number}` | ProcessPullRequestWebhook |
| Get PR files | `GET /repos/{owner}/{repo}/pulls/{number}/files` | DiffCollector |
| Get file contents | `GET /repos/{owner}/{repo}/contents/{path}` | FileContextCollector |
| Post review | `POST /repos/{owner}/{repo}/pulls/{number}/reviews` | PostRunAnnotations |
| Post comment | `POST /repos/{owner}/{repo}/issues/{number}/comments` | PostCommandResponse |
| Get repositories | `GET /installation/repositories` | SyncInstallationRepositories |

### Rate Limiting

GitHub API has rate limits. Sentinel handles them:

- **Primary rate limit**: 5000 requests/hour per installation
- **Secondary rate limits**: Per-endpoint limits for expensive operations
- **Retry with backoff**: Automatic retry on 429 responses

---

## Posting Review Comments

### Review Structure

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     GITHUB REVIEW STRUCTURE                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  PR REVIEW                                                                      │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │                                                                          │    │
│  │  🤖 Sentinel Bot                                                        │    │
│  │  ─────────────────                                                      │    │
│  │                                                                          │    │
│  │  ## 🔍 Review Summary                                                   │    │
│  │                                                                          │    │
│  │  **Verdict:** ✅ Approve                                                │    │
│  │  **Risk Level:** Low                                                    │    │
│  │                                                                          │    │
│  │  This PR introduces a well-structured authentication middleware...      │    │
│  │                                                                          │    │
│  │  ### Strengths                                                          │    │
│  │  - Clean separation of concerns                                         │    │
│  │  - Good error handling                                                  │    │
│  │                                                                          │    │
│  │  ### Concerns                                                           │    │
│  │  - Missing rate limiting (see inline comment)                          │    │
│  │                                                                          │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  INLINE COMMENTS (Attached to specific lines)                                   │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │                                                                          │    │
│  │  app/Middleware/Auth.php, line 45                                       │    │
│  │  ┌──────────────────────────────────────────────────────────────┐      │    │
│  │  │ 45 │ $user = User::where('token', $token)->first();          │      │    │
│  │  └──────────────────────────────────────────────────────────────┘      │    │
│  │                                                                          │    │
│  │  🟡 **Medium: Missing Rate Limiting**                                   │    │
│  │                                                                          │    │
│  │  This endpoint could be vulnerable to brute force attacks.              │    │
│  │                                                                          │    │
│  │  **Suggestion:**                                                        │    │
│  │  ```php                                                                 │    │
│  │  RateLimiter::for('auth', fn($request) =>                              │    │
│  │      Limit::perMinute(5)->by($request->ip())                           │    │
│  │  );                                                                     │    │
│  │  ```                                                                    │    │
│  │                                                                          │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Annotation Styles

| Style | How It's Posted | Best For |
|-------|-----------------|----------|
| `review` | PR review with inline comments | Most cases |
| `comment` | Individual issue comments | Simple feedback |
| `check` | Check Run with annotations | CI integration |

---

## Installation Management

### Installation States

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     INSTALLATION LIFECYCLE                                       │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ACTIVE                                                                         │
│  └─ Normal operation, receiving webhooks, can make API calls                   │
│                                                                                  │
│  SUSPENDED                                                                      │
│  └─ Org admin suspended the app                                                │
│     • Webhooks stop                                                            │
│     • API calls fail                                                           │
│     • Sentinel marks installation as suspended                                  │
│                                                                                  │
│  DELETED                                                                        │
│  └─ App uninstalled from org/user                                              │
│     • Sentinel receives installation.deleted webhook                           │
│     • Installation record soft-deleted                                         │
│     • Repositories remain but become inactive                                  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Multiple Installations

A workspace can have multiple installations:
- Multiple GitHub organizations
- Personal accounts + organization accounts

```
Workspace: Acme Engineering
├─ Installation: acme-org (GitHub Org)
│  ├─ acme-org/backend
│  └─ acme-org/frontend
│
└─ Installation: alice-personal (Personal account)
   └─ alice/side-project
```

---

## Disconnecting GitHub

### Disconnect Flow

```bash
DELETE /api/v1/workspaces/{workspace}/github/connection
```

This action:
1. Marks Connection as disconnected
2. Marks all Installations as inactive
3. Repositories remain but stop receiving reviews
4. **Does NOT uninstall from GitHub** (user must do that manually)

---

## Error Handling

### Common Issues

| Issue | Cause | Resolution |
|-------|-------|------------|
| Webhook signature invalid | Wrong secret configured | Check GITHUB_WEBHOOK_SECRET |
| Installation token expired | Token refresh failed | Re-authenticate installation |
| Repository not found | Repo removed from installation | Sync repositories |
| Rate limit exceeded | Too many API calls | Wait for reset, optimize calls |
| Permission denied | App permissions changed | Re-install with correct permissions |

---

## Code Locations

| Component | Location |
|-----------|----------|
| Webhook Controller | `app/Http/Controllers/GitHub/WebhookController.php` |
| Connection Controller | `app/Http/Controllers/GitHub/ConnectionController.php` |
| Repository Controller | `app/Http/Controllers/GitHub/RepositoryController.php` |
| Initiate Connection | `app/Actions/GitHub/InitiateGitHubConnection.php` |
| Handle Installation | `app/Actions/GitHub/HandleGitHubInstallation.php` |
| Sync Repositories | `app/Actions/GitHub/SyncInstallationRepositories.php` |
| PR Webhook Job | `app/Jobs/GitHub/ProcessPullRequestWebhook.php` |
| Comment Webhook Job | `app/Jobs/GitHub/ProcessIssueCommentWebhook.php` |
| Post Annotations | `app/Actions/Reviews/PostRunAnnotations.php` |
| Models | `app/Models/Connection.php`, `Installation.php`, `Repository.php` |

---

## Best Practices

1. **Start with select repositories** - Don't enable all repos immediately
2. **Configure API keys first** - Reviews won't work without BYOK keys
3. **Test on a non-critical repo** - Validate configuration before rolling out
4. **Use branch protections** - Sentinel works alongside required reviews
5. **Monitor webhook delivery** - GitHub's webhook UI shows delivery status

---

*Next: [Analytics Dashboard](./06-ANALYTICS-DASHBOARD.md) - Code quality metrics and trends*
