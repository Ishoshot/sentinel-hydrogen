<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetFindingsDistribution;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get findings distribution by severity level.
 */
final class FindingsDistributionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace, GetFindingsDistribution $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace),
        ]);
    }
}
