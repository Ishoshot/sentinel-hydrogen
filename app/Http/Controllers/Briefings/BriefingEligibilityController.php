<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\GetWorkspaceBriefingEligibility;
use App\Models\Briefing;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class BriefingEligibilityController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Workspace $workspace,
        GetWorkspaceBriefingEligibility $getWorkspaceBriefingEligibility,
    ): JsonResponse {
        Gate::authorize('viewAny', [Briefing::class, $workspace]);

        $eligibility = $getWorkspaceBriefingEligibility->handle($workspace);

        return response()->json([
            'can_generate' => $eligibility->allowed,
            'restriction_reason' => $eligibility->reason,
        ]);
    }
}
