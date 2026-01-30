<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetRepositoryActivity;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get repository activity showing runs per repository.
 */
final class RepositoryActivityController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetRepositoryActivity $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days()),
        ]);
    }
}
