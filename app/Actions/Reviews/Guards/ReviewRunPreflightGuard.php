<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Guards;

use App\Actions\Reviews\ValueObjects\ReviewRunPreflightResult;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Models\Run;
use App\Services\Plans\PlanLimitEnforcer;

final readonly class ReviewRunPreflightGuard
{
    /**
     * Create a new preflight checker instance.
     */
    public function __construct(private PlanLimitEnforcer $planLimitEnforcer) {}

    /**
     * Perform run eligibility checks.
     */
    public function check(Run $run): ReviewRunPreflightResult
    {
        if (! in_array($run->status, [RunStatus::Queued, RunStatus::InProgress], true)) {
            return ReviewRunPreflightResult::unchanged();
        }

        $run->loadMissing(['repository.settings', 'repository.installation']);

        $repository = $run->repository;
        if ($repository === null) {
            return ReviewRunPreflightResult::unchanged();
        }

        $installation = $repository->installation;
        if ($installation === null || ! $installation->isActive()) {
            return ReviewRunPreflightResult::skip(
                SkipReason::InstallationInactive,
                'Installation is inactive or missing.'
            );
        }

        $workspace = $run->workspace ?? $repository->workspace;
        if ($workspace === null) {
            return ReviewRunPreflightResult::proceed();
        }

        $subscriptionCheck = $this->planLimitEnforcer->ensureActiveSubscription($workspace);
        if (! $subscriptionCheck->allowed) {
            return ReviewRunPreflightResult::skip(
                SkipReason::PlanLimitReached,
                $subscriptionCheck->message ?? 'Subscription is not active.'
            );
        }

        return ReviewRunPreflightResult::proceed();
    }
}
