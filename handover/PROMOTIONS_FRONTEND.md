# Frontend Handover: Promotions / Promo Codes

> **Context**: Replaced the nonprofit discount system with a flexible promotion code system.

---

## Summary

Users can now apply a **promo code** when upgrading their subscription. The backend validates the code and applies discounts accordingly.

---

## API Changes

### Upgrade Subscription Endpoint

**`POST /api/workspaces/{workspace}/subscription/upgrade`**

#### Request Body

```typescript
interface UpgradeSubscriptionRequest {
    plan_tier: "free" | "team" | "business" | "enterprise";
    promo_code?: string | null; // NEW: Optional promo code
}
```

#### Success Response (with Polar configured)

```typescript
interface UpgradeResponse {
    data: {
        checkout_url: string;
        promotion?: {
            code: string; // The applied promo code
            discount: string; // Human-readable, e.g. "20% off" or "$10.00 off"
        } | null;
    };
    message: string;
}
```

#### Error Response (invalid promo code)

```typescript
// HTTP 422
interface PromoCodeError {
    message: string;
    errors: {
        promo_code: string[]; // e.g. ["Invalid promotion code."]
    };
}
```

#### Possible Promo Code Error Messages

| Message                                       |
| --------------------------------------------- |
| `Invalid promotion code.`                     |
| `This promotion is no longer active.`         |
| `This promotion has expired.`                 |
| `This promotion is not yet active.`           |
| `This promotion has reached its usage limit.` |

---

## Removed Fields

The following fields have been **removed** from the `PlanResource`:

-   `nonprofit_discount_percent`
-   `nonprofit_price_monthly_cents`
-   `nonprofit_price_monthly`

The `nonprofit` boolean parameter has been **removed** from the upgrade request.

---

## Frontend Implementation Notes

1. **Add promo code input** on the plan upgrade / checkout flow
2. **Validate on submit** - show error message from `errors.promo_code[0]` if validation fails
3. **Display applied discount** - if `promotion` is returned in success response, show the discount info before redirecting to checkout
4. **Case insensitive** - promo codes are case-insensitive and trimmed on the backend

---

## Example Usage

```typescript
// Upgrade with promo code
const response = await fetch(
    `/api/workspaces/${workspaceId}/subscription/upgrade`,
    {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            plan_tier: "team",
            promo_code: "LAUNCH2026",
        }),
    }
);

const data = await response.json();

if (!response.ok) {
    // Show error: data.errors.promo_code[0]
    return;
}

if (data.data.promotion) {
    // Optionally show: "Discount applied: 25% off"
}

// Redirect to checkout
window.location.href = data.data.checkout_url;
```
