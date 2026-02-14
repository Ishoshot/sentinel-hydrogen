<?php

declare(strict_types=1);

namespace App\Services\Plans\Support;

use App\Models\Workspace;
use App\Services\Plans\ValueObjects\PlanLimitResult;

/**
 * Checks a single usage count against a plan limit and returns an allow/deny result.
 */
final readonly class PlanUsageLimitChecker
{
    public function __construct(
        private PlanLimitEventLogger $eventLogger,
    ) {}

    /**
     * Check whether the current usage exceeds the plan limit.
     *
     * @param  array<string, int>  $metadata  Additional metadata to include in the event log
     */
    public function check(
        Workspace $workspace,
        ?int $limit,
        int $usage,
        string $limitName,
        string $messageFormat,
        array $metadata = [],
    ): PlanLimitResult {
        if ($limit === null) {
            return PlanLimitResult::allow();
        }

        if ($usage < $limit) {
            return PlanLimitResult::allow();
        }

        $message = sprintf($messageFormat, $usage, $limit);

        $this->eventLogger->log($workspace, $limitName, $message, array_merge(
            $metadata,
            ['limit' => $limit],
        ));

        return PlanLimitResult::deny($message, $limitName);
    }
}
