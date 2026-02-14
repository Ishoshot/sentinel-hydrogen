<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Models\Repository;
use Illuminate\Support\Facades\Log;

final class ManualReviewEligibilityChecker
{
    /**
     * Evaluate whether a repository can run a manual review.
     *
     * @param  array{repository_id: int, pr_number: int, sender: string}  $context
     */
    public function check(Repository $repository, array $context): ManualReviewEligibilityResult
    {
        $installation = $repository->installation;

        if ($installation === null) {
            return ManualReviewEligibilityResult::deny('Repository installation not found.');
        }

        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Manual review requested but auto-review disabled', $context);

            return ManualReviewEligibilityResult::deny(
                'Code reviews are disabled for this repository. Enable auto-review in repository settings to use this feature.'
            );
        }

        return ManualReviewEligibilityResult::allow($installation);
    }
}
