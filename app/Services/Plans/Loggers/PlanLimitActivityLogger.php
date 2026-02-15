<?php

declare(strict_types=1);

namespace App\Services\Plans\Loggers;

use App\Actions\Activities\LogActivity;
use App\Enums\Billing\PlanFeature;
use App\Enums\Workspace\ActivityType;
use App\Models\Workspace;

final readonly class PlanLimitActivityLogger
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(Workspace $workspace, PlanFeature|string $limitType, string $message, ?array $metadata = null): void
    {
        $limitTypeValue = $limitType instanceof PlanFeature ? $limitType->value : $limitType;

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::PlanLimitReached,
            description: $message,
            metadata: array_merge(['limit_type' => $limitTypeValue], $metadata ?? []),
        );
    }
}
