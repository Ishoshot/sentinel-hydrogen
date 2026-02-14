<?php

declare(strict_types=1);

namespace App\Actions\Commands\Support;

use App\Enums\Commands\CommandType;

final class IssueCommentReviewCommandGate
{
    /**
     * Determine whether the command should trigger a PR manual review.
     */
    public function shouldTriggerManualReview(
        CommandType $commandType,
        bool $isPullRequest,
        mixed $issueNumber,
    ): bool {
        return $commandType === CommandType::Review && $isPullRequest && $issueNumber !== null;
    }

    /**
     * Determine whether review guidance should be posted for issue comments.
     */
    public function shouldPostIssueReviewGuidance(
        CommandType $commandType,
        bool $isPullRequest,
        mixed $issueNumber,
    ): bool {
        return $commandType === CommandType::Review && ! $isPullRequest && $issueNumber !== null;
    }
}
