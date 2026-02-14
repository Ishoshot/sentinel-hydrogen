# Sentinel - Integrations

This file defines contracts for external providers and APIs.

---

## Integration Principles

- Integrations are behind explicit service boundaries.
- External calls are isolated from business orchestration.
- Provider-specific payload quirks are normalized centrally.

---

## Integration Domains

### GitHub

- App installation lifecycle
- webhook validation and dispatch
- repository, pull request, comment, and check-run operations

### AI Providers

- provider/model routing
- BYOK credential usage
- fallback behavior and usage tracking

### Slack

- OAuth install flow
- channel discovery and message delivery
- webhook delivery support

### Billing (Polar)

- checkout/session orchestration
- subscription sync and webhook processing
- discounts and plan transitions

---

## Configuration Precedence

1. Environment/application defaults
2. Workspace-level integration settings
3. Repository-level overrides (where supported)

Behavior must be deterministic and documented where precedence applies.

---

## Security Rules

- Store credentials securely; never log secrets.
- Verify webhook signatures before processing payloads.
- Treat all provider payloads as untrusted input.

---

## Failure and Retry Rules

- Failures must map to typed/domain-safe outcomes.
- Retries must avoid duplicate side effects.
- Telemetry must include provider, operation, and correlation identifiers.

---

## Extensibility Rules

When adding a new provider:

1. Introduce/extend contracts at the boundary.
2. Add provider-specific client/adapter.
3. Add tests for happy path, auth failure, and provider outage behavior.

