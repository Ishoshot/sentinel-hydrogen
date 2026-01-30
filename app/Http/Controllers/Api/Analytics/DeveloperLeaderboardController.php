<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetDeveloperLeaderboard;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get developer leaderboard showing most active contributors.
 */
final class DeveloperLeaderboardController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetDeveloperLeaderboard $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days(), $request->limit()),
        ]);
    }
}
