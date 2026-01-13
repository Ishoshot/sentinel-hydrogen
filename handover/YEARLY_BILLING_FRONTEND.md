# Frontend Handover: Yearly Billing & Plan Descriptions

> **Context**: Added support for yearly billing intervals, plan descriptions, and renamed tiers.

---

## Summary

Users can now choose between **monthly** and **yearly** billing when upgrading. Each plan now has a description for better UX.

---

## Plan Tiers

| Tier                  | Description                                                                             | Monthly | Yearly |
| --------------------- | --------------------------------------------------------------------------------------- | ------- | ------ |
| **Foundation** (free) | For individual developers and small projects getting started with trusted code review.  | $0      | $0     |
| **Illuminate**        | For growing teams that want deeper insight and consistent code quality across projects. | $20     | $210   |
| **Orchestrate**       | For professional teams coordinating code quality at scale across multiple repositories. | $50     | $450   |
| **Sanctum**           | For organizations that require governance, security, and reliability guarantees.        | $200    | $2,100 |

---

## API Changes

### Plans Endpoint

**`GET /api/plans`**

#### Response Fields

```typescript
interface Plan {
    id: number;
    tier: "foundation" | "illuminate" | "orchestrate" | "sanctum";
    description: string | null;
    monthly_runs_limit: number | null;
    team_size_limit: number | null;
    features: Record<string, boolean>;
    price_monthly_cents: number | null;
    price_monthly: string | null;
    price_yearly_cents: number | null;
    price_yearly: string | null;
    yearly_savings_percent: number;
    currency: string | null;
}
```

#### Example Response

```json
{
    "data": [
        {
            "id": 1,
            "tier": "foundation",
            "description": "For individual developers and small projects getting started with trusted code review.",
            "price_monthly_cents": 0,
            "price_monthly": "0.00",
            "price_yearly_cents": 0,
            "price_yearly": "0.00",
            "yearly_savings_percent": 0
        },
        {
            "id": 2,
            "tier": "illuminate",
            "description": "For growing teams that want deeper insight and consistent code quality across projects.",
            "price_monthly_cents": 2000,
            "price_monthly": "20.00",
            "price_yearly_cents": 21000,
            "price_yearly": "210.00",
            "yearly_savings_percent": 12
        }
    ]
}
```

---

### Upgrade Subscription Endpoint

**`POST /api/workspaces/{workspace}/subscription/upgrade`**

#### Request Body

```typescript
interface UpgradeSubscriptionRequest {
    plan_tier: "illuminate" | "orchestrate" | "sanctum";
    billing_interval?: "monthly" | "yearly"; // Defaults to 'monthly'
    promo_code?: string | null;
}
```

#### Success Response

```typescript
interface UpgradeResponse {
    data: {
        checkout_url: string;
        billing_interval: "monthly" | "yearly"; // NEW: Confirms interval
        promotion?: {
            code: string;
            discount: string;
        } | null;
    };
    message: string;
}
```

#### Validation Error (invalid interval)

```typescript
// HTTP 422
{
  "message": "Billing interval must be monthly or yearly.",
  "errors": {
    "billing_interval": ["Billing interval must be monthly or yearly."]
  }
}
```

---

## Frontend Implementation Notes

### 1. Plan Pricing Display

Show both monthly and yearly options with savings badge:

```
Illuminate
$20/mo billed monthly
$210/yr billed yearly (Save 12%)
```

### 2. Billing Toggle

Add a toggle/tabs to switch between monthly and yearly pricing:

```
[Monthly] [Yearly - Save 12%]
```

### 3. Upgrade Flow

When user clicks upgrade:

1. Capture selected `billing_interval` ('monthly' or 'yearly')
2. Send to upgrade endpoint
3. Redirect to `checkout_url`

### 4. Plan Descriptions

Display `description` below plan name for context:

```
Illuminate
For growing teams that want deeper insight and consistent code quality across projects.
```

---

## Example Usage

```typescript
// Upgrade with yearly billing
const response = await fetch(
    `/api/workspaces/${workspaceId}/subscription/upgrade`,
    {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            plan_tier: "illuminate",
            billing_interval: "yearly", // or 'monthly'
            promo_code: "LAUNCH2026", // optional
        }),
    }
);

const data = await response.json();

if (!response.ok) {
    // Handle errors
    return;
}

// Redirect to Polar checkout
window.location.href = data.data.checkout_url;
```

---

## Environment Variables (Backend)

The following env vars need to be configured for Polar billing:

```env
# Polar API Configuration
POLAR_ACCESS_TOKEN=           # Organization Access Token from Polar dashboard
POLAR_API_URL=https://api.polar.sh
POLAR_WEBHOOK_SECRET=

# Polar Product IDs (get from Polar dashboard → Products → Copy Product ID)
POLAR_PRODUCT_ILLUMINATE_MONTHLY=
POLAR_PRODUCT_ORCHESTRATE_MONTHLY=
POLAR_PRODUCT_SANCTUM_MONTHLY=
POLAR_PRODUCT_ILLUMINATE_YEARLY=
POLAR_PRODUCT_ORCHESTRATE_YEARLY=
POLAR_PRODUCT_SANCTUM_YEARLY=
```

## How It Works

1. **Checkout**: Backend calls Polar API (`POST /v1/checkouts`) with product ID and metadata
2. **Customer Portal**: Backend calls Polar API (`POST /v1/customer-sessions`) for authenticated portal access
3. **Webhooks**: Polar sends events (checkout.completed, subscription.updated, etc.) to your webhook endpoint
