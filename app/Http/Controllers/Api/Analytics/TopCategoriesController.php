<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Enums\FindingCategory;
use App\Models\Finding;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Get top finding categories by frequency.
 */
final class TopCategoriesController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);

        $categories = $workspace->findings()
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(static function (Finding $finding): array {
                /** @var FindingCategory|string|null $categoryAttribute */
                $categoryAttribute = $finding->getAttribute('category');

                if ($categoryAttribute instanceof FindingCategory) {
                    $category = $categoryAttribute->value;
                } elseif (is_string($categoryAttribute)) {
                    $category = $categoryAttribute;
                } else {
                    $category = '';
                }

                /** @var int|string $count */
                $count = $finding->getAttribute('count');

                return [
                    'category' => $category,
                    'count' => (int) $count,
                ];
            });

        return response()->json([
            'data' => $categories,
        ]);
    }
}
