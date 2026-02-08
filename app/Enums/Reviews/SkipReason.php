<?php

declare(strict_types=1);

namespace App\Enums\Reviews;

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
    case OrphanedRepository = 'orphaned_repository';
    case InstallationInactive = 'installation_inactive';
}
