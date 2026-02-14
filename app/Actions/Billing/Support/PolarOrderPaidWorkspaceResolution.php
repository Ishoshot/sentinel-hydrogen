<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Models\Workspace;

final readonly class PolarOrderPaidWorkspaceResolution
{
    /**
     * Create a new workspace resolution result.
     */
    public function __construct(
        public ?Workspace $workspace,
        public ?string $planTier,
        public ?int $workspaceId,
    ) {}
}
