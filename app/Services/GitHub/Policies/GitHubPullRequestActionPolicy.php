<?php

declare(strict_types=1);

namespace App\Services\GitHub\Policies;

use App\Enums\GitHub\PullRequestAction;

final class GitHubPullRequestActionPolicy
{
    /**
     * Determine if a pull request action should trigger a review.
     */
    public function shouldTriggerReview(string $action): bool
    {
        $prAction = PullRequestAction::tryFrom($action);

        return $prAction?->shouldTriggerReview() ?? false;
    }

    /**
     * Determine if a pull request action should sync run metadata.
     */
    public function shouldSyncMetadata(string $action): bool
    {
        $prAction = PullRequestAction::tryFrom($action);

        return $prAction?->shouldSyncMetadata() ?? false;
    }

    /**
     * Determine if a pull request action should clean up PR-scoped indexes.
     */
    public function shouldCleanupPreIndex(string $action): bool
    {
        $prAction = PullRequestAction::tryFrom($action);

        return $prAction?->shouldCleanupPreIndex() ?? false;
    }
}
