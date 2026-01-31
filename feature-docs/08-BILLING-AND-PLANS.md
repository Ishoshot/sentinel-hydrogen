# Billing & Plans

## The Business Model That Respects Your Budget

Traditional AI code review tools charge you twice: once for the platform, and again for AI usage. Sentinel flips this script with a **Bring Your Own Key (BYOK)** model that puts you in control of AI costs while providing a transparent subscription for platform access.

Think of it like buying a coffee machine (Sentinel) versus buying individual coffees (AI costs). You own the machine, and you buy your own beans at whatever price works for you.

---

## Billing Philosophy

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     SENTINEL'S BILLING MODEL                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                                                                          │   │
│  │        SUBSCRIPTION FEE              DIRECT TO AI PROVIDER               │   │
│  │   ┌─────────────────────┐         ┌─────────────────────────────┐       │   │
│  │   │                     │         │                             │       │   │
│  │   │  Platform Access    │         │  Claude API                 │       │   │
│  │   │  Feature Limits     │    +    │  OpenAI API                 │       │   │
│  │   │  Support Level      │         │  (Your keys, your account)  │       │   │
│  │   │  Team Management    │         │                             │       │   │
│  │   │                     │         │                             │       │   │
│  │   └─────────────────────┘         └─────────────────────────────┘       │   │
│  │         You pay                         You pay                          │   │
│  │        Sentinel                      AI Provider                         │   │
│  │                                                                          │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  Benefits:                                                                       │
│  • Full cost transparency                                                        │
│  • No hidden markups                                                            │
│  • Enterprise compliance (data stays in your account)                           │
│  • Switch providers anytime                                                      │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Plan Tiers

Sentinel offers four tiers designed for different team sizes and needs:

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                    PLAN COMPARISON                                                │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                                   │
│  FOUNDATION                 ILLUMINATE               ORCHESTRATE              SANCTUM             │
│  $0/month                   $20/month                $50/month                $200/month          │
│  ─────────                  ──────────               ───────────              ──────────          │
│                                                                                                   │
│  ┌──────────────┐          ┌──────────────┐        ┌──────────────┐        ┌──────────────┐      │
│  │              │          │              │        │              │        │              │      │
│  │   Individual │          │   Growing    │        │ Professional │        │  Enterprise  │      │
│  │   Developers │          │   Teams      │        │    Teams     │        │Organizations │      │
│  │              │          │              │        │              │        │              │      │
│  └──────────────┘          └──────────────┘        └──────────────┘        └──────────────┘      │
│                                                                                                   │
│  20 runs/month              500 runs/month          2,000 runs/month        Unlimited runs       │
│  50 commands/month          200 commands/month      1,000 commands/month    Unlimited commands   │
│  2 team members             5 team members          Unlimited members       Unlimited members    │
│  Community support          Email support           Priority support        Dedicated support    │
│                                                                                                   │
│  ✓ Briefings                ✓ Briefings             ✓ Briefings             ✓ Briefings          │
│  ✓ BYOK                     ✓ BYOK                  ✓ BYOK                  ✓ BYOK               │
│  ✗ Custom Guidelines        ✓ Custom Guidelines     ✓ Custom Guidelines     ✓ Custom Guidelines  │
│  ✗ Priority Queue           ✓ Priority Queue        ✓ Priority Queue        ✓ Priority Queue     │
│  ✗ API Access               ✗ API Access            ✓ API Access            ✓ API Access         │
│  ✗ SSO                      ✗ SSO                   ✗ SSO                   ✓ SSO                │
│  ✗ Audit Logs               ✗ Audit Logs            ✗ Audit Logs            ✓ Audit Logs         │
│                                                                                                   │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

### Detailed Plan Breakdown

| Feature | Foundation | Illuminate | Orchestrate | Sanctum |
|---------|------------|------------|-------------|---------|
| **Monthly Price** | $0 | $20 | $50 | $200 |
| **Annual Price** | $0 | $210/yr | $450/yr | $2,100/yr |
| **Monthly Runs** | 20 | 500 | 2,000 | Unlimited |
| **Monthly Commands** | 50 | 200 | 1,000 | Unlimited |
| **Team Size** | 2 | 5 | Unlimited | Unlimited |
| **Daily Briefings** | 2 | 10 | 50 | Unlimited |
| **Weekly Briefings** | 5 | 30 | Unlimited | Unlimited |
| **Monthly Briefings** | 10 | 100 | Unlimited | Unlimited |
| **Support** | Community | Email | Priority | Dedicated |

---

## Features by Plan

### Core Features (All Plans)

Every Sentinel plan includes:

- **BYOK Support**: Use your own API keys for AI providers
- **Briefings**: AI-generated team reports (with tier-based limits)
- **Dashboard Access**: Full analytics and insights
- **GitHub Integration**: Full webhook and review support

### Illuminate & Above

```yaml
custom_guidelines: true    # Define review focus areas
priority_queue: true       # Faster review processing
```

### Orchestrate & Above

```yaml
api_access: true           # Programmatic API access
# Full API for automation and integrations
```

### Sanctum Only

```yaml
sso_enabled: true          # Enterprise SSO integration
audit_logs: true           # Detailed audit trail
# Dedicated support channel
```

---

## Limit Enforcement

Sentinel enforces limits at runtime with clear, actionable feedback:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     LIMIT ENFORCEMENT FLOW                                       │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Review Request                                                                  │
│        │                                                                         │
│        ▼                                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                Check 1: Active Subscription?                             │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │  subscription_status ∈ [active, trialing]?                              │   │
│  │                                                                          │   │
│  │     YES → Continue                                                       │   │
│  │     NO  → "Your subscription is inactive. Upgrade to restore access."   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│        │                                                                         │
│        ▼                                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                Check 2: Within Run Limit?                                │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │  runsThisMonth < plan.monthly_runs_limit?                               │   │
│  │  (null limit = unlimited)                                                │   │
│  │                                                                          │   │
│  │     YES → Continue                                                       │   │
│  │     NO  → "Run limit reached (500/500). Upgrade your plan."             │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│        │                                                                         │
│        ▼                                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                Check 3: Provider Key Available?                          │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │  workspace.providerKeys.any()?                                           │   │
│  │                                                                          │   │
│  │     YES → Execute Review                                                 │   │
│  │     NO  → "No API keys configured. Add a provider key to enable AI."    │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Enforcement Behavior

| Limit Type | Hard/Soft | Behavior When Exceeded |
|------------|-----------|------------------------|
| Runs | Hard | Blocked, clear message in PR |
| Commands | Hard | Blocked, response explains limit |
| Team Size | Hard | Invitation blocked |
| Briefings | Soft | Warning shown, generation continues |

---

## BYOK (Bring Your Own Key)

The BYOK model is central to Sentinel's architecture:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     BYOK ARCHITECTURE                                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │  WORKSPACE CONFIGURATION                                                  │  │
│  ├──────────────────────────────────────────────────────────────────────────┤  │
│  │                                                                           │  │
│  │  Provider Keys:                                                           │  │
│  │  ┌──────────────┐  ┌──────────────┐                                      │  │
│  │  │  Anthropic   │  │   OpenAI     │                                      │  │
│  │  │  sk-ant-**** │  │  sk-*******  │                                      │  │
│  │  │  [Primary]   │  │  [Fallback]  │                                      │  │
│  │  └──────────────┘  └──────────────┘                                      │  │
│  │                                                                           │  │
│  │  Model Preference: claude-sonnet-4-5-20250929                            │  │
│  │  Fallback Enabled: Yes                                                    │  │
│  │                                                                           │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
│                                                                                  │
│  AI Request Flow:                                                               │
│                                                                                  │
│  1. Sentinel prepares review context                                            │
│  2. Checks configured provider keys                                             │
│  3. Routes to preferred provider (Anthropic)                                    │
│  4. If primary fails, tries fallback (OpenAI)                                   │
│  5. Usage billed directly to YOUR provider account                              │
│                                                                                  │
│                                                                                  │
│  Key Storage:                                                                   │
│  • Keys encrypted at rest (AES-256)                                            │
│  • Never logged or exposed                                                      │
│  • Scoped to workspace only                                                     │
│  • Deletable anytime                                                            │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Supported Providers

| Provider | Models | Status |
|----------|--------|--------|
| Anthropic | Claude Sonnet 4.5, Claude Sonnet 4, Claude Haiku | Supported |
| OpenAI | GPT-4o, GPT-4 Turbo, GPT-4 | Supported |

### Cost Transparency

With BYOK, you see exactly what AI costs:

```yaml
# Example: Review of a 500-line PR
Context tokens: ~8,000
Response tokens: ~2,000
Total tokens: ~10,000

# At typical Anthropic pricing:
# Claude Sonnet 4: ~$0.03 input + ~$0.15 output = ~$0.18 per review

# Your monthly estimate (500 runs):
# ~$90/month in AI costs (paid directly to Anthropic)
```

---

## Workspace Creation Rules

Sentinel has specific rules for creating multiple workspaces:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     WORKSPACE CREATION RULES                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  First Workspace:                                                               │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │  ✓ Always allowed                                                        │   │
│  │  ✓ Can be on any plan (including free Foundation)                       │   │
│  │  ✓ No restrictions                                                       │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  Additional Workspaces:                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │  ✓ Requires ALL existing workspaces on paid plans (Illuminate+)         │   │
│  │  ✗ Blocked if ANY workspace is on Foundation (free)                     │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│                                                                                  │
│  Example Scenarios:                                                             │
│                                                                                  │
│  Scenario 1: New user wants first workspace                                     │
│  ┌──────────────────────────────────────────────────────────────────┐          │
│  │  Existing: None                                                   │          │
│  │  Result: ✓ ALLOWED (can choose any plan)                         │          │
│  └──────────────────────────────────────────────────────────────────┘          │
│                                                                                  │
│  Scenario 2: User on Foundation wants second workspace                          │
│  ┌──────────────────────────────────────────────────────────────────┐          │
│  │  Existing: [Workspace A - Foundation]                             │          │
│  │  Result: ✗ BLOCKED                                                │          │
│  │  Message: "Upgrade existing workspaces to a paid plan first"     │          │
│  └──────────────────────────────────────────────────────────────────┘          │
│                                                                                  │
│  Scenario 3: User on Illuminate wants second workspace                          │
│  ┌──────────────────────────────────────────────────────────────────┐          │
│  │  Existing: [Workspace A - Illuminate]                             │          │
│  │  Result: ✓ ALLOWED                                                │          │
│  └──────────────────────────────────────────────────────────────────┘          │
│                                                                                  │
│  Scenario 4: Mixed plans, wants third workspace                                 │
│  ┌──────────────────────────────────────────────────────────────────┐          │
│  │  Existing: [Workspace A - Orchestrate, Workspace B - Foundation]  │          │
│  │  Result: ✗ BLOCKED (Workspace B is on free plan)                 │          │
│  └──────────────────────────────────────────────────────────────────┘          │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Subscription Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     SUBSCRIPTION STATE MACHINE                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│                         ┌─────────────┐                                         │
│                         │   trialing  │                                         │
│                         └──────┬──────┘                                         │
│                                │                                                 │
│              ┌─────────────────┼─────────────────┐                              │
│              │                 │                 │                              │
│              ▼                 ▼                 ▼                              │
│       ┌──────────┐      ┌──────────┐      ┌──────────┐                         │
│       │  active  │◄────►│  paused  │      │ canceled │                         │
│       └────┬─────┘      └──────────┘      └──────────┘                         │
│            │                                                                     │
│            │ Payment failed                                                      │
│            ▼                                                                     │
│       ┌──────────┐                                                              │
│       │past_due  │──────► Reviews blocked, data retained                        │
│       └──────────┘                                                              │
│                                                                                  │
│                                                                                  │
│  Status Behavior:                                                               │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │  active   │ Full access, all features enabled                           │   │
│  │  trialing │ Full access during trial period                             │   │
│  │  paused   │ Reviews blocked, data accessible                            │   │
│  │  past_due │ Reviews blocked, grace period for payment                   │   │
│  │  canceled │ Reviews blocked, data retained per policy                   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Upgrade Behavior

- **Immediate effect**: New limits and features available instantly
- **Pro-rated billing**: Pay difference for remaining period
- **No data migration**: Same workspace, enhanced capabilities

### Downgrade Behavior

- **End of billing cycle**: Changes take effect at renewal
- **Existing data retained**: Nothing deleted automatically
- **New actions blocked**: If they exceed lower plan limits

---

## Usage Tracking

Sentinel tracks usage for billing and analytics:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     USAGE RECORD STRUCTURE                                       │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  usage_records table:                                                           │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │  workspace_id      │  Foreign key to workspaces                         │   │
│  │  period_start      │  2025-01-01 00:00:00                               │   │
│  │  period_end        │  2025-01-31 23:59:59                               │   │
│  │  runs_count        │  342                                                │   │
│  │  commands_count    │  156                                                │   │
│  │  findings_count    │  1,247                                              │   │
│  │  annotations_count │  892                                                │   │
│  │  tokens_estimated  │  2,340,000                                          │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  Billing Period:                                                                │
│  • Resets on the 1st of each month (UTC)                                       │
│  • Usage counts toward the billing month they occurred in                       │
│  • Historical usage accessible for all retained data                           │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Integration with Polar

Sentinel uses Polar for subscription management:

```yaml
# Polar Integration
billing_service: PolarBillingService

features:
  - Subscription creation and management
  - Plan changes (upgrades/downgrades)
  - Customer portal access
  - Usage-based billing support
  - Webhook integration

portal_access:
  route: /api/v1/workspaces/{workspace}/billing/portal
  returns: redirect_url
  # Users redirected to Polar's hosted portal
```

---

## API Endpoints

### Subscription Management

| Method | Path | Description |
|--------|------|-------------|
| GET | `/workspaces/{workspace}/subscription` | Get current subscription |
| GET | `/workspaces/{workspace}/subscription/usage` | Get usage stats |
| POST | `/workspaces/{workspace}/subscription/change` | Change plan |
| GET | `/workspaces/{workspace}/billing/portal` | Get portal redirect |

### Plan Information

| Method | Path | Description |
|--------|------|-------------|
| GET | `/plans` | List all available plans |
| GET | `/plans/{id}` | Get plan details |

---

## Code Locations

| Component | Location |
|-----------|----------|
| Plan Model | `app/Models/Plan.php` |
| Subscription Model | `app/Models/Subscription.php` |
| Plan Limit Enforcer | `app/Services/Plans/PlanLimitEnforcer.php` |
| Plan Defaults | `app/Support/PlanDefaults.php` |
| Plan Tier Enum | `app/Enums/Billing/PlanTier.php` |
| Plan Feature Enum | `app/Enums/Billing/PlanFeature.php` |
| Subscription Status Enum | `app/Enums/Billing/SubscriptionStatus.php` |
| Billing Service | `app/Services/Billing/PolarBillingService.php` |
| Subscription Actions | `app/Actions/Subscriptions/*.php` |

---

## Best Practices

1. **Start with Foundation** - Try Sentinel free before committing
2. **Add your own keys** - BYOK gives you control over AI costs
3. **Monitor usage** - Dashboard shows runs remaining
4. **Upgrade proactively** - Avoid blocked reviews during busy periods
5. **Annual billing** - Save ~12.5% with yearly subscriptions

---

*Next: [Design Patterns](./09-DESIGN-PATTERNS.md) - Architectural patterns used throughout Sentinel*
