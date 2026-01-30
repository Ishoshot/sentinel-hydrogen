<?php

declare(strict_types=1);

namespace App\Enums\GitHub;

/**
 * GitHub Pull Request webhook actions.
 */
enum PullRequestAction: string
{
    // Actions that trigger a new review
    case Opened = 'opened';
    case Synchronize = 'synchronize';
    case Reopened = 'reopened';

    // Actions that sync metadata only
    case Edited = 'edited';
    case Labeled = 'labeled';
    case Unlabeled = 'unlabeled';
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case ReviewRequested = 'review_requested';
    case ReviewRequestRemoved = 'review_request_removed';
    case ConvertedToDraft = 'converted_to_draft';
    case ReadyForReview = 'ready_for_review';

    // Other actions
    case Closed = 'closed';

    /**
     * Check if this action should trigger a new review.
     */
    public function shouldTriggerReview(): bool
    {
        return in_array($this, [
            self::Opened,
            self::Synchronize,
            self::Reopened,
        ], true);
    }

    /**
     * Check if this action should sync metadata on an existing run.
     */
    public function shouldSyncMetadata(): bool
    {
        return in_array($this, [
            self::Edited,
            self::Labeled,
            self::Unlabeled,
            self::Assigned,
            self::Unassigned,
            self::ReviewRequested,
            self::ReviewRequestRemoved,
            self::ConvertedToDraft,
            self::ReadyForReview,
        ], true);
    }
}
