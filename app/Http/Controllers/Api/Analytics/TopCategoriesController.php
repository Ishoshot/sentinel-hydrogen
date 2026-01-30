<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetTopCategories;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get top finding categories by frequency.
 */
final class TopCategoriesController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetTopCategories $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->limit()),
        ]);
    }
}
