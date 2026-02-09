<?php

declare(strict_types=1);

namespace App\Enums\Briefings;

enum BriefingLimitReasonCode: string
{
    case FeatureDisabled = 'feature_disabled';
    case BriefingInactive = 'briefing_inactive';
    case PlanNotEligible = 'plan_not_eligible';
    case FreeAllowanceExhausted = 'free_allowance_exhausted';
    case RateLimitReached = 'rate_limit_reached';
    case ConcurrentLimitReached = 'concurrent_limit_reached';
    case InsufficientData = 'insufficient_data';
}
