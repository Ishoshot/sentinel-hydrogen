<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reasons why a review run was skipped or failed.
 *
 * Used for posting explanatory comments to GitHub.
 */
enum SkipReason: string
{
    case NoProviderKeys = 'no_provider_keys';
    case RunFailed = 'run_failed';
    case PlanLimitReached = 'plan_limit_reached';
}
