# Sentinel - Upcoming Development

> **Purpose**: This document tracks upcoming features and enhancements. Completed work is moved to `DEVELOPMENT_STATE.md`.

---

## Current State

**Last Completed Phase**: Phase 6 - BYOK Provider Key Management
**Tests Passing**: 499
**Documentation**: See `DEVELOPMENT_STATE.md` for completed implementation details

---

## Upcoming Phases

### Phase 7: Plans & Billing

**Status: NOT STARTED**

Implement subscription tiers, usage metering, and limit enforcement.

#### Subscription Tiers

| Plan           | Monthly Price | Runs/Month | Team Size   | Support   |
| -------------- | ------------- | ---------- | ----------- | --------- |
| **Free**       | $0            | 20         | 1 workspace | Community |
| **Team**       | $49           | 500        | Unlimited   | Email     |
| **Business**   | $199          | 2,000      | Unlimited   | Priority  |
| **Enterprise** | Custom        | Unlimited  | Unlimited   | Dedicated |

#### Implementation Tasks

**7A: Plan Model & Seed Data**

-   Create `Plan` model with tier-based limits
-   Seed default plans (Free, Team, Business, Enterprise)
-   Plan attributes:
    -   `tier` (enum: free, team, business, enterprise)
    -   `monthly_runs_limit`
    -   `team_size_limit`
    -   `features` (JSONB: array of enabled features)
    -   `price_monthly` (cents)

**7B: Subscription Management**

-   Create `Subscription` model (workspace → plan relationship)
-   Default: All workspaces start on Free plan
-   Migrations: Add `plan_id`, `subscription_status`, `trial_ends_at` to `workspaces`
-   Actions:
    -   `CreateSubscription` - Assign plan to workspace
    -   `UpgradeSubscription` - Move to higher tier
    -   `CancelSubscription` - Downgrade to Free
-   Policies: Only workspace owners can manage subscriptions

**7C: Usage Metering**

-   Create `UsageRecord` model for tracking consumption
-   Track:
    -   `runs_count` - Reviews executed this period
    -   `findings_count` - Total findings generated
    -   `annotations_count` - Annotations posted
    -   `period_start`, `period_end` - Billing cycle
-   Daily aggregation job: `AggregateUsage`
-   Reset usage on billing cycle (monthly)

**7D: Limit Enforcement**

-   Service: `PlanLimitEnforcer`
-   Check before creating Run:
    -   If `runs_count >= plan.monthly_runs_limit`: Block with clear message
    -   If `team_size >= plan.team_size_limit`: Block invitations
-   Graceful degradation:
    -   When limit reached, show upgrade prompt
    -   Don't fail silently - always explain why blocked
-   Activity logging for limit events

**7E: Plan Features**

-   Feature flags per plan:
    -   `byok_enabled` - Bring Your Own Key (Free+)
    -   `custom_guidelines` - Team guidelines (Team+)
    -   `priority_queue` - Fast-track reviews (Team+)
    -   `api_access` - REST API tokens (Business+)
    -   `sso_enabled` - Single Sign-On (Enterprise only)
    -   `audit_logs` - Extended audit logs (Enterprise only)
    -   `team_size_limit` - Number of team members allowed (Free: 2, Team: 5, Business: Unlimited, Enterprise: Unlimited)
-   Check feature access before operations:
    -   BYOK: Check `plan.features.byok_enabled` before storing provider key
    -   Guidelines: Check `plan.features.custom_guidelines` before syncing

**7F: Billing Integration (Stripe)**

-   Not in initial scope - manual plan assignment via admin panel
-   Future: Stripe Checkout for plan upgrades
-   Future: Webhook handler for Stripe events

**7G: Frontend API**

-   Controllers:
    -   `PlanController` - List available plans
    -   `SubscriptionController` - View current plan, upgrade
-   Resources:
    -   `PlanResource` - Plan details with pricing
    -   `SubscriptionResource` - Current subscription status
    -   `UsageResource` - Current period usage stats
-   Routes:
    -   `GET /api/plans` - List available plans
    -   `GET /api/workspaces/{workspace}/subscription` - Current subscription
    -   `GET /api/workspaces/{workspace}/usage` - Usage stats for period
    -   `POST /api/workspaces/{workspace}/subscription/upgrade` - Upgrade plan (future)

**7H: Tests**

-   Plan model tests
-   Subscription management tests
-   Usage metering tests
-   Limit enforcement tests (block runs when limit reached)
-   Feature access tests

---

### Phase 8: Dashboards & Analytics

**Status: NOT STARTED**

Provide insights into code quality trends, review effectiveness, and workspace activity.

#### Implementation Tasks

**8A: Workspace Dashboard**

-   Overview metrics:
    -   Total reviews (this month, all time)
    -   Findings by severity (chart)
    -   Top categories (security, performance, etc.)
    -   Review velocity (reviews per day/week)
-   Recent activity feed (last 20 activities)
-   Quick actions (connect new repo, view settings, etc.)

**8B: Repository Metrics**

-   Per-repository dashboard:
    -   Review history (list of runs with status)
    -   Finding trends over time (line chart)
    -   Most common categories
    -   Average findings per review
    -   Time to fix (if we track finding resolution)
-   Compare repositories (which repos have most issues)

**8C: Analytics API**

-   Controllers:
    -   `AnalyticsController` - Workspace-level metrics
    -   `RepositoryAnalyticsController` - Repository-level metrics
-   Aggregation queries:
    -   Group findings by severity, category, time period
    -   Count runs by status (completed, failed, skipped)
    -   Calculate trends (week-over-week, month-over-month)
-   Caching: Cache expensive aggregations (Redis, 15 min TTL)

**8D: Exportable Reports**

-   Generate CSV/PDF reports
-   Filters: Date range, repository, severity
-   Use cases:
    -   Monthly quality reports for stakeholders
    -   Compliance audit trails
    -   Team retrospectives

**8E: Tests**

-   Analytics calculation tests
-   Aggregation query tests
-   Report generation tests

---

## Future Enhancements (Backlog)

### Context Engine Improvements

**Context Display for Frontend**
Currently, the Context Engine feeds data to the AI but doesn't expose it to the frontend.

Enhancement:

-   Store collected context in Run's `metadata` field
-   Add `context` field to `RunResource` API response
-   Frontend UI to display:
    -   Linked issues with their details
    -   Previous review history on same PR
    -   Repository guidelines (README/CONTRIBUTING)
    -   PR discussion context used in review
    -   Files excluded by filters (with reasons)

Benefits:

-   Users understand _what_ the AI saw
-   Transparency builds trust
-   Helps debug unexpected review results

**Additional Context Collectors**

-   `CIStatusCollector` (priority: 55) - Include CI/CD build status and test results
-   `SecurityScanCollector` (priority: 65) - Include SAST/dependency scan results from GitHub Security
-   `DependencyCollector` (priority: 75) - Flag dependency updates (package.json, composer.json, etc.)
-   `TestCoverageCollector` (priority: 85) - Include code coverage reports (if available)

**New Context Filters**

-   `LanguageSpecificFilter` - Remove irrelevant code for specific languages (e.g., skip `.go` files if repo is 95% Python)
-   `GeneratedCodeFilter` - Skip auto-generated files (Swagger, Protobuf, etc.)
-   `ChangeFrequencyFilter` - Prioritize files changed frequently (higher churn = higher risk)

---

### Review System Improvements

**Run Cancellation**
When a new commit arrives while a previous review is still queued/in-progress:

-   Cancel the older run (mark as `Cancelled`)
-   Queue the new run
-   Prevents reviewing stale code
-   Saves compute resources

**Review Annotations Improvements**

-   Support for GitHub Check Runs (not just PR comments)
-   Support for GitLab Merge Request comments
-   Batch annotation posting (reduce API calls)
-   Annotation edit/update (if user fixes issue)

**AI Provider Fallback**
If Anthropic fails:

-   Automatically retry with OpenAI (if key configured)
-   Log provider failures for monitoring
-   Surface provider errors to users

**Review Templates**
Per-repository review templates for consistent formatting:

-   Custom prompt additions
-   Custom finding categories
-   Language-specific checks (Laravel for PHP, Rails for Ruby, etc.)

---

### Multi-Provider Support (Future)

**GitLab Integration**

-   Similar to GitHub integration
-   OAuth flow for GitLab
-   Webhook handling (merge requests, push events)
-   API client for GitLab API

**Bitbucket Integration**

-   OAuth flow for Bitbucket
-   Webhook handling (pull requests)
-   API client for Bitbucket API

**Self-Hosted Git (Generic)**

-   Support for self-hosted GitLab, Gitea, Gogs
-   Webhook configuration UI
-   Token-based authentication

---

### Team Collaboration Features

**Review Comments & Discussion**

-   Allow team members to comment on findings
-   Mark findings as "Accepted", "Wontfix", "Invalid"
-   Threaded discussions per finding
-   Notify relevant team members

**Finding Templates**

-   Create reusable finding templates
-   "SQL injection in {file}" → auto-populate with code snippet
-   Share templates across team

**Review Rules Presets**

-   Pre-built rule sets for common frameworks:
    -   "Laravel Best Practices"
    -   "React Security"
    -   "Node.js Performance"
-   Import preset, customize as needed

---

### Performance & Scalability

**Queue Optimization**

-   Review priority based on PR size (small PRs → fast queue)
-   Parallel review execution (split large PRs into chunks)
-   Dedicated queues per workspace (enterprise feature)

**Caching Strategy**

-   Cache GitHub API responses (README, CONTRIBUTING, etc.)
-   Cache guideline files (TTL: 1 hour)
-   Cache repository settings (invalidate on push to default branch)

**Database Optimization**

-   Index optimization for large workspaces
-   Partition `runs` and `findings` tables by month (archive old data)
-   Read replicas for analytics queries

---

### Security & Compliance

**Audit Logs (Enterprise)**

-   Extended audit trail:
    -   Who viewed what (runs, findings, settings)
    -   Configuration changes
    -   Team member actions
    -   Export logs for compliance

**SSO Integration (Enterprise)**

-   SAML 2.0 support
-   OIDC support
-   Sync team members from identity provider

**SOC 2 Compliance**

-   Data retention policies
-   Encryption at rest (database, backups)
-   Encryption in transit (TLS 1.3)
-   Regular security audits

---

### Developer Experience

**CLI Tool**

-   `sentinel review` - Trigger review locally
-   `sentinel check` - Validate `.sentinel/config.yaml`
-   `sentinel test` - Run rules against local code
-   Authentication via API token

**IDE Integration**

-   VS Code extension
-   JetBrains plugin
-   Real-time feedback on unsaved code

**API Documentation**

-   OpenAPI spec generation
-   Interactive API explorer (Swagger/Redoc)
-   API client SDKs (Python, JavaScript, Go)

---

## Design Decisions (Reference)

### Why Repository-Scoped Provider Keys?

Instead of workspace-scoped, we chose repository-scoped BYOK because:

-   Different repos may use different AI providers (some use Claude, others OpenAI)
-   Different repos may have different billing/budgets
-   Avoids "one key to rule them all" security risk
-   Allows experimentation (try Claude on one repo, OpenAI on another)

### Why Replace (Not Merge) Enabled Rules?

When users configure `.sentinel/config.yaml` with `categories: {performance: false}`, we **replace** the default enabled_rules instead of merging. This allows users to truly disable categories, rather than having them always enabled by defaults.

### Why Skip Reviews on Config Errors?

When `.sentinel/config.yaml` has syntax errors, we skip the review entirely (with PR comment explaining the error) instead of falling back to defaults. This prevents confusion where users think their config is active when it's actually being ignored.

---

## Questions / Clarifications Needed

1. **Billing**: Do we want to integrate Stripe immediately, or start with manual plan assignment?
2. **Analytics**: Should we store aggregated metrics (pre-calculated) or compute on-demand?
3. **Multi-Provider**: Priority order for GitLab vs Bitbucket support?
4. **Enterprise Features**: Which features are most critical for enterprise adoption?

---

_Last Updated: 2026-01-11_
_This document will be updated as phases are completed and moved to DEVELOPMENT_STATE.md_
