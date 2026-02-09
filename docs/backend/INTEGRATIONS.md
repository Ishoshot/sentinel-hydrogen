# Sentinel – Integrations

This document defines how Sentinel integrates with external systems.
It establishes architectural boundaries, lifecycle rules, and extensibility guidelines.

All integrations MUST conform to this document.

---

## Integration Philosophy

Sentinel is **platform-agnostic by design**.

Integrations are treated as:

-   replaceable
-   isolated
-   interface-driven
-   explicitly configured

No integration is considered a core dependency.
The system must continue to operate gracefully when integrations fail or are unavailable.

---

## Integration Categories

Sentinel integrations fall into three primary categories:

1. Source Control Providers
2. AI Providers
3. Notification Integrations
4. Billing and External Services (future)

Each category follows consistent design principles.

---

## Source Control Providers

### Definition

A **Source Control Provider** represents a platform that hosts repositories
and emits events related to code changes.

Examples:

-   GitHub (current)
-   GitLab (future)

---

### Provider Abstraction

All source control providers implement a shared interface.

Responsibilities include:

-   authentication and authorization
-   installation lifecycle management
-   repository discovery
-   webhook event normalization
-   annotation and comment publishing

Provider-specific logic MUST NOT leak outside the integration layer.

---

### Installation Lifecycle

An Installation represents Sentinel being installed into a provider account.

Lifecycle stages:

1. Installation created
2. Permissions granted
3. Repositories selected
4. Installation activated
5. Installation suspended or removed

Installation state changes are persisted and auditable.

---

### Webhook Handling

-   All inbound webhooks are verified
-   Webhooks are normalized into internal events
-   Duplicate events are safely ignored
-   Webhooks enqueue jobs; they do not perform work directly

Webhook payloads are never trusted implicitly.

---

## AI Providers

### Definition

An **AI Provider** represents an external system capable of performing code analysis
or review tasks.

Examples:

-   OpenAI
-   Anthropic
-   Others supported via PrismPHP

---

### Provider Routing

AI providers are accessed exclusively through **PrismPHP**.

Routing decisions are based on:

-   workspace configuration
-   provider key availability
-   policy constraints
-   provider health and eligibility

Sentinel never hard-codes provider-specific logic.

---

### BYOK Enforcement

-   All AI providers require a valid Provider Key
-   Provider Keys are workspace-scoped
-   Providers without keys are excluded from routing

No fallback provider is assumed.

---

### Failure Handling

If an AI provider:

-   times out
-   returns invalid output
-   becomes unavailable

Sentinel:

-   records the failure
-   produces a ReviewResult with failure context
-   does not retry indefinitely
-   does not switch providers silently unless policy allows

Failures must be explicit and visible.

---

## Notification Integrations

### Slack Integration

Slack is integrated via **OAuth 2.0**, providing a workspace-level notification channel with bot token access.

#### OAuth Flow

1. User clicks "Add to Slack" — backend generates state, stores on `SlackIntegration`, returns OAuth URL
2. User authorizes in Slack — redirected to `/api/slack/callback` with `code` and `state`
3. Backend validates state (constant-time comparison, 15-minute expiry), exchanges code for bot token
4. Token, team info, and bot details stored on `SlackIntegration`; activity logged
5. User selects a default channel from a dropdown (populated via `conversations.list`)

#### Model

`SlackIntegration` — one per workspace (unique constraint on `workspace_id`).

Fields: `access_token` (encrypted), `bot_user_id`, `slack_team_id`, `team_name`, `scope`, `authed_user_id`, `channel_id`, `channel_name`, `state`, `state_expires_at`, `is_active`, `connected_at`.

Helpers: `hasValidToken()`, `isFullyConfigured()` (token + channel), `hasValidState()`.

#### Service Contract

`SlackServiceContract` defines:

-   `exchangeCodeForToken(code, redirectUri)` — POST `oauth.v2.access`, return token data
-   `sendMessage(integration, channelId, text, blocks)` — POST `chat.postMessage`
-   `listChannels(integration)` — GET `conversations.list`
-   `testConnection(integration)` — POST `auth.test`
-   `revokeToken(integration)` — POST `auth.revoke`

Bound via `SlackServiceProvider`.

#### Routes

| Method | Path                                          | Controller                              | Name                          |
|--------|-----------------------------------------------|-----------------------------------------|-------------------------------|
| GET    | `/slack/callback`                             | SlackCallbackController@__invoke        | slack.callback                |
| GET    | `/workspaces/{workspace}/slack/integration`   | SlackIntegrationController@show         | slack.integration.show        |
| POST   | `/workspaces/{workspace}/slack/connect`       | SlackIntegrationController@store        | slack.integration.connect     |
| DELETE | `/workspaces/{workspace}/slack/disconnect`    | SlackIntegrationController@destroy      | slack.integration.destroy     |
| GET    | `/workspaces/{workspace}/slack/channels`      | SlackChannelController@index            | slack.integration.channels    |
| PATCH  | `/workspaces/{workspace}/slack/channel`       | SlackChannelController@update           | slack.integration.update-channel |

The callback route is public (no auth middleware), alongside the GitHub callback.

#### Actions

-   `InitiateSlackConnection` — generates state, creates/updates pending integration, builds OAuth URL
-   `HandleSlackCallback` — validates state, exchanges code for token, activates integration, logs activity
-   `UpdateSlackChannel` — saves selected channel_id and channel_name
-   `DisconnectSlack` — revokes token (best-effort), deletes integration, logs activity

#### Authorization

-   Any workspace member can view integration status and channels
-   Only owners and admins can connect, disconnect, or update channel

#### Briefing Delivery

Briefing subscriptions reference the workspace's Slack integration.
The `DeliverBriefing` job loads `workspace.slackIntegration`, checks `isFullyConfigured()`, and uses `SlackServiceContract::sendMessage()` with the integration's `channel_id`.

---

## Integration Configuration

### Workspace-Level Configuration

Defines:

-   enabled providers
-   provider credentials
-   default behavior

---

### Repository-Level Configuration

Defines:

-   review enablement
-   thresholds and limits
-   overrides to workspace defaults

---

### Configuration Precedence

1. Repository configuration file (if present)
2. Repository settings (dashboard)
3. Workspace defaults
4. System defaults

This order is fixed and deterministic.

---

## Security Considerations

-   Secrets are encrypted at rest
-   Access tokens are rotated when possible
-   Webhook secrets are validated
-   Least-privilege scopes are enforced

Integration failures must never expose sensitive data.

---

## Extensibility Rules

When adding a new integration:

-   implement the appropriate interface
-   register via a Service Provider
-   avoid modifying existing integrations
-   add tests and documentation

New integrations must not require changes to core domains.

---

## Observability

Integration operations must:

-   emit structured logs
-   include correlation IDs
-   record latency and error rates

Integration health is observable and measurable.

---

## General Rules

-   No integration logic in controllers
-   No provider-specific branching outside adapters
-   No implicit assumptions about provider behavior
-   All external calls are bounded and timeout-protected

---

This document defines Sentinel’s integration contract.
