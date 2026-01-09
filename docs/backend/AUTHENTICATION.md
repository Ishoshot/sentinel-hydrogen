# Sentinel – Authentication & Authorization

This document defines how authentication and authorization work in Sentinel.
It covers user authentication, API authentication, and provider integration authentication.

All authentication and authorization implementations MUST conform to this document.
This is a hard contract.

---

## Authentication Philosophy

Sentinel uses a **layered authentication model**:

-   Users authenticate via OAuth providers
-   Browser access uses session-based authentication
-   Programmatic access uses token-based authentication
-   Provider integrations authenticate via provider-specific installation mechanisms

Authentication is strict by default.
Unauthenticated access is never permitted to protected resources.

---

## User Authentication

### OAuth Providers

Users authenticate into Sentinel exclusively via OAuth.

Supported providers:

-   GitHub
-   Google
-   GitLab (future)

Sentinel does NOT support username/password authentication.

---

### OAuth Login Flow

1. User initiates login by selecting an OAuth provider
2. User is redirected to the provider’s consent screen
3. Provider redirects back with an authorization code
4. Sentinel exchanges the code for an access token
5. Sentinel retrieves the authenticated user profile
6. A User record is created or matched
7. A browser session is established

---

### Session Authentication (Dashboard)

-   Browser access uses Laravel session-based authentication
-   Sessions are managed via secure, HTTP-only cookies
-   Session lifetime is configurable
-   Sessions are invalidated on logout
-   Sessions are user-scoped and workspace-agnostic

Session authentication is used **only** for interactive dashboard access.

---

### User Identity Matching

When a user authenticates:

-   If a verified email matches an existing User, link the provider identity
-   If no match exists, create a new User
-   Provider identities are persisted for future logins

Rules:

-   Email must be verified by the provider where supported
-   Unverified emails MUST NOT be auto-linked
-   A User may have multiple linked provider identities

Provider identities are immutable audit records.

---

## API Authentication

### Token-Based Authentication (Laravel Sanctum)

Sentinel uses **Laravel Sanctum** for programmatic API authentication.

Sanctum tokens provide:

-   bearer-token authentication
-   scoped abilities
-   expiration and revocation
-   auditability

---

### API Token Usage

API tokens are used for:

-   CLI tools
-   automation
-   external integrations
-   non-browser clients

Browser sessions MUST NOT rely on bearer tokens.

---

### Token Issuance

API tokens may be issued:

-   via the Sentinel dashboard
-   programmatically by authorized users

Tokens are:

-   scoped to a User
-   scoped to a single Workspace by default
-   associated with explicit abilities

Workspace-unscoped tokens are considered elevated and must be explicitly created.

---

### Token Abilities

Tokens define allowed actions.

Examples:

-   `read:repositories`
-   `write:repositories`
-   `read:runs`
-   `trigger:reviews`
-   `manage:workspace`

Both token abilities and user authorization MUST pass for access.

---

### Token Lifecycle

-   Tokens may define expiration dates
-   Tokens may be revoked at any time
-   Revoked tokens are immediately invalid
-   Token usage is logged for audit purposes
-   Tokens are displayed only once at creation

Plaintext tokens are never retrievable after creation.

---

### API Request Authentication

API requests authenticate using:

Authorization: Bearer <token>

If authentication fails:

-   Return `401 Unauthorized`
-   Do not reveal token existence or validity details

---

## Provider Integration Authentication

### Installation-Based Authentication

Source control and external providers authenticate via
**provider-specific installation mechanisms**.

Examples:

-   GitHub App installations
-   GitLab app integrations (future)

Installation authentication is distinct from user OAuth.

---

### Installation Flow

1. A user initiates provider installation
2. Provider redirects to Sentinel callback
3. Sentinel receives installation credentials
4. Installation is linked to a Workspace
5. Installation access credentials are stored securely

Installations are Workspace-scoped.

---

### Installation Tokens

Provider installation tokens:

-   are encrypted at rest
-   are scoped to the installation
-   are never exposed to clients
-   are rotated or refreshed where supported

If token refresh fails, the installation is disabled until resolved.

---

## Authorization

### Workspace-Scoped Authorization

All authorization in Sentinel is scoped to a Workspace.

A User may:

-   belong to multiple Workspaces
-   have different roles per Workspace
-   access only resources within authorized Workspaces

---

### Roles

Members have a role within a Workspace Team:

-   **Owner** – full control, billing, deletion
-   **Admin** – manage settings, members, integrations
-   **Member** – view data and trigger reviews

Roles are centrally defined and consistent.

---

### Authorization Evaluation Order

Authorization checks are evaluated in this order:

1. Authentication (user or token)
2. Workspace membership
3. Role permissions
4. Plan and feature entitlements
5. Token abilities (if applicable)
6. Resource ownership

All checks must pass.

---

### Policies

Laravel Policies are the single source of truth for authorization.

Policies evaluate:

-   workspace membership
-   role permissions
-   plan-based feature access
-   token abilities (if present)

Policies are invoked **before Actions execute**.

---

## Security Rules

### General Security

-   All authentication endpoints require HTTPS
-   Sensitive data is encrypted at rest
-   Authentication attempts are rate-limited
-   OAuth callbacks are rate-limited
-   Token authentication failures are throttled

---

### Token Security

-   Tokens are hashed before storage
-   Plaintext tokens are never stored
-   Token rotation is encouraged
-   Compromised tokens are immediately revocable

---

### Session Security

-   Sessions use secure, HTTP-only cookies
-   Sessions are invalidated on logout
-   Sessions may be invalidated on suspicious activity (future)

---

### OAuth Security

-   OAuth state parameters prevent CSRF
-   Redirect URIs are strictly validated
-   Provider tokens are never exposed to frontend clients

---

### CSRF & CORS

-   Session-authenticated routes require CSRF protection
-   Bearer-token routes do not use CSRF
-   Strict CORS rules apply to token-authenticated endpoints

---

## Observability & Audit

Authentication and authorization events MUST be logged:

-   login success and failure
-   token creation and revocation
-   provider installation events
-   authorization denials

Logs include:

-   `user_id` (when available)
-   `workspace_id` (when applicable)
-   `ip_address`
-   `user_agent`
-   `correlation_id`

Auditability is mandatory.

---

## General Rules

-   No authentication logic in controllers
-   No direct token comparison (use timing-safe checks)
-   No implicit trust of client-provided identity
-   All authentication flows go through dedicated services
-   Authorization is enforced server-side only

---

This document defines Sentinel’s authentication and authorization contract.
