# Sentinel - Authentication and Authorization

This file defines identity, token, and access-control rules.

---

## Authentication Modes

### Dashboard Authentication

- Session-based authentication for web UI.
- OAuth login providers are used for identity bootstrap.
- User identity must map deterministically to a Sentinel user.

### API Authentication

- Laravel Sanctum personal access tokens for API clients.
- Token abilities must be explicit and minimal.
- Token issuance and revocation must be auditable.

### Integration Authentication

- GitHub App installation tokens for repository operations.
- Provider credentials stored and used through integration services only.

---

## Authorization Model

All authorization is workspace-scoped.

Role model (minimum contract):

- `owner`: full workspace control
- `admin`: operational management
- `member`: standard product operations

Rules:

- Authorization before mutation.
- Repository and run actions require workspace membership.
- Admin-only paths must be explicit in policies and routes.

---

## Security Rules

- Never call `env()` outside config files.
- Never log secrets, access tokens, private keys, or raw provider payloads with credentials.
- Use signed/expiring URLs for share surfaces.
- Enforce CSRF and secure session defaults for browser auth.

---

## Token and Session Lifecycle

- Tokens are revocable and ability-scoped.
- Expiration/rotation policy must be enforced by config.
- Session invalidation must happen on explicit logout and critical account events.

---

## Audit Requirements

Track authentication and authorization-sensitive operations:

- login and provider link/unlink events
- token creation/revocation
- workspace role changes
- integration install/uninstall or credential failures

