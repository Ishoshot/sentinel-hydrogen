<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use App\Services\Briefings\ValueObjects\BriefingParameters;

final readonly class GetWorkspaceBriefingEligibility
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
    ) {}

    /**
     * Determine whether the workspace can generate briefings.
     */
    public function handle(Workspace $workspace, ?BriefingParameters $parameters = null): BriefingLimitResult
    {
        $parameters ??= BriefingParameters::fromArray([]);

        return $this->limitEnforcer->canGenerateForWorkspace($workspace, $parameters);
    }
}
