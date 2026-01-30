<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\Reviews\FindingCategory;
use App\Models\Finding;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Get top finding categories by frequency.
 */
final readonly class GetTopCategories
{
    /**
     * Get top categories for a workspace.
     *
     * @return Collection<int, array{category: string, count: int}>
     */
    public function handle(Workspace $workspace, int $limit = 10): Collection
    {
        return $workspace->findings()
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
    }
}
