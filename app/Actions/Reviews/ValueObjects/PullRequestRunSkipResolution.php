<?php

declare(strict_types=1);

namespace App\Actions\Reviews\ValueObjects;

use App\Models\Workspace;

final readonly class PullRequestRunSkipResolution
{
    /**
     * Create a new pull request run skip resolution.
     */
    public function __construct(
        public ?Workspace $workspace,
        public ?string $skipReason,
        public bool $planLimitTriggered = false,
        public ?string $skipReasonCode = null,
    ) {}

    /**
     * Determine if the run should be created as skipped.
     */
    public function shouldSkip(): bool
    {
        return $this->skipReason !== null;
    }

    /**
     * Determine if a plan limit skip comment should be posted.
     */
    public function shouldPostPlanLimitComment(): bool
    {
        if (! $this->planLimitTriggered) {
            return false;
        }

        return in_array($this->skipReasonCode, ['runs_limit', 'subscription_inactive'], true);
    }
}
