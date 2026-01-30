<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Contracts;

use App\Enums\Reviews\SkipReason;
use App\Models\Run;

/**
 * Contract for posting skip/failure reason comments to pull requests.
 */
interface PostsSkipReasonComment
{
    /**
     * Post a skip/failure reason comment to the pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Run $run, SkipReason $reason, ?string $detail = null): ?int;
}
