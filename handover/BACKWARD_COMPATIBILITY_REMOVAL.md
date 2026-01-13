# Backend: Backward Compatibility Removal

> **Context**: Since the product is not shipped yet, we are removing all backward compatibility code to keep the codebase clean.

---

## Summary of Findings

The following backward compatibility patterns have been identified:

---

## 1. Workspace `settings['tier']` Fallback (REMOVE)

**File**: `app/Models/Workspace.php` (lines 167-180)

**Current Code**:

```php
public function getCurrentTier(): string
{
    if ($this->plan !== null) {
        return $this->plan->tier;
    }

    // LEGACY: Fallback to settings['tier'] - REMOVE THIS
    $settings = $this->settings;
    if (is_array($settings) && isset($settings['tier']) && is_string($settings['tier'])) {
        return $settings['tier'];
    }

    return 'free';
}
```

**Action**: Remove the `settings['tier']` fallback. Tier should ONLY come from `plan->tier`.

**Frontend Impact**: None - API responses already use `plan.tier`.

---

## 2. Legacy Tier Names in `JobContext` (UPDATE)

**File**: `app/Services/Queue/JobContext.php`

### `isPaidTier()` (line 120-123)

```php
public function isPaidTier(): bool
{
    // OLD: ['paid', 'pro', 'team', 'enterprise']
    return in_array($this->tier, ['paid', 'pro', 'team', 'enterprise'], true);
}
```

**New tier names**: `foundation`, `illuminate`, `orchestrate`, `sanctum`

**Action**: Update to use new tier names:

```php
public function isPaidTier(): bool
{
    return in_array($this->tier, ['illuminate', 'orchestrate', 'sanctum'], true);
}
```

### `isEnterpriseTier()` (line 128-131)

```php
public function isEnterpriseTier(): bool
{
    return $this->tier === 'enterprise';
}
```

**Action**: Update to use new tier name:

```php
public function isEnterpriseTier(): bool
{
    return $this->tier === 'sanctum';
}
```

**Frontend Impact**: None - internal queue routing only.

---

## 3. Deprecated Method in `ReviewPromptBuilder` (REMOVE)

**File**: `app/Services/Reviews/ReviewPromptBuilder.php` (lines 52-70)

```php
/**
 * @deprecated Use buildUserPromptFromBag() with ContextBag instead
 */
public function buildUserPrompt(array $context): string
```

**Action**: Remove this deprecated method entirely.

**Frontend Impact**: None - internal service method.

---

## 4. `polar_discount_id` Field (ADD MIGRATION)

**File**: `app/Services/Billing/PolarBillingService.php` (line 81)

```php
$payload['discount_id'] = $promotion->polar_discount_id ?? null;
```

**Issue**: The `polar_discount_id` field doesn't exist on the `Promotion` model.

**Action**: Either:

1. Add a migration to add `polar_discount_id` column to `promotions` table, OR
2. Remove this line if discounts are handled differently by Polar

**Frontend Impact**: If promotions need Polar discount IDs, backend needs migration.

---

## 5. Comment Text Cleanup (UPDATE)

Update these user-facing messages to use new tier names:

| File                                  | Current                                                       | Should Be                                   |
| ------------------------------------- | ------------------------------------------------------------- | ------------------------------------------- |
| `CancelSubscriptionController.php:15` | "Cancel a workspace subscription and downgrade to Free plan." | "...downgrade to Foundation plan."          |
| `CancelSubscriptionController.php:33` | "Subscription canceled and downgraded to Free plan."          | "...downgraded to Foundation plan."         |
| `PlanLimitEnforcer.php:157`           | "creating a Free plan if none exists"                         | "creating a Foundation plan if none exists" |

**Frontend Impact**: API response messages will change.

---

## 6. Workspace `getCurrentTier()` Return Value (UPDATE)

**File**: `app/Models/Workspace.php` (line 179)

```php
return 'free';  // Default fallback
```

**Action**: Change to `'foundation'` to match new tier naming.

---

## New Plan Tiers (Reminder)

| Tier            | Description                           |
| --------------- | ------------------------------------- |
| **foundation**  | Free tier for individual developers   |
| **illuminate**  | $20/month - Growing teams             |
| **orchestrate** | $50/month - Professional teams        |
| **sanctum**     | $200/month - Enterprise organizations |

---

## API Response Changes

After these changes, the following API responses will be affected:

1. **Cancel subscription response**: Message changes from "Free" to "Foundation"
2. **Workspace tier**: Always comes from `plan.tier`, no fallback to settings

---

## Migration Checklist

-   [ ] Update `Workspace::getCurrentTier()` - remove settings fallback, return 'foundation' as default
-   [ ] Update `JobContext::isPaidTier()` - use new tier names
-   [ ] Update `JobContext::isEnterpriseTier()` - use 'sanctum'
-   [ ] Remove `ReviewPromptBuilder::buildUserPrompt()` deprecated method
-   [ ] Update comment messages to use "Foundation" instead of "Free"
-   [ ] Decide on `polar_discount_id` field handling
-   [ ] Simplify `PrismReviewEngine::normalizeFinding()` - remove legacy field support
-   [ ] Simplify `ExecuteReviewRun::createFinding()` - remove legacy metadata fields

---

## 7. AI Finding Field Names - Legacy Support (SIMPLIFY)

**File**: `app/Services/Reviews/PrismReviewEngine.php` (lines 470-536)

The AI prompt uses standardized field names, but the code still supports legacy names:

### Legacy Field Mappings

| Legacy Field | New Field                          | Action                      |
| ------------ | ---------------------------------- | --------------------------- |
| `rationale`  | `impact`                           | Use `impact` only           |
| `suggestion` | `replacement_code` + `explanation` | Use new fields only         |
| `patch`      | `replacement_code`                 | Use `replacement_code` only |
| `tags`       | (removed)                          | Remove support              |

**Current Code** (line 470-476):

```php
// Support both 'impact' (new) and 'rationale' (legacy) for backward compatibility
$rationale = '';
if (isset($finding['impact']) && is_string($finding['impact'])) {
    $rationale = $finding['impact'];
} elseif (isset($finding['rationale']) && is_string($finding['rationale'])) {
    $rationale = $finding['rationale'];
}
```

**Action**: Remove legacy field support. Use `impact` only.

---

## 8. ExecuteReviewRun Legacy Metadata (SIMPLIFY)

**File**: `app/Actions/Reviews/ExecuteReviewRun.php` (lines 127-138)

```php
$metadata = Arr::only($findingData, [
    // Legacy fields
    'suggestion',
    'patch',
    'references',
    'tags',
    'rationale',
    // New enhanced fields
    'current_code',
    'replacement_code',
    'explanation',
]);
```

**Action**: Remove legacy fields:

```php
$metadata = Arr::only($findingData, [
    'current_code',
    'replacement_code',
    'explanation',
    'references',
]);
```

---

## Standardized Finding Structure

After cleanup, findings should use:

```json
{
    "severity": "critical|high|medium|low|info",
    "category": "security|correctness|...",
    "title": "Clear title",
    "description": "What the issue is",
    "impact": "Why it matters",
    "confidence": 0.95,
    "file_path": "path/to/file.ext",
    "line_start": 42,
    "line_end": 45,
    "current_code": "code with issue",
    "replacement_code": "fixed code",
    "explanation": "why this fix works",
    "references": ["https://..."]
}
```

**Removed Fields**: `rationale`, `suggestion`, `patch`, `tags`
