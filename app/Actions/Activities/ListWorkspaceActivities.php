<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * List activities for a workspace with filtering.
 */
final class ListWorkspaceActivities
{
    /**
     * List activities for the workspace.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(
        Workspace $workspace,
        ?string $type = null,
        ?string $category = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Activity::query()
            ->with('actor')
            ->where('workspace_id', $workspace->id)
            ->orderBy('created_at', 'desc');

        $this->applyTypeFilter($query, $type);
        $this->applyCategoryFilter($query, $category);

        return $query->paginate($perPage);
    }

    /**
     * Apply type filter.
     *
     * @param  Builder<Activity>  $query
     */
    private function applyTypeFilter(Builder $query, ?string $type): void
    {
        if ($type === null || ! in_array($type, ActivityType::values(), true)) {
            return;
        }

        $query->where('type', $type);
    }

    /**
     * Apply category filter.
     *
     * @param  Builder<Activity>  $query
     */
    private function applyCategoryFilter(Builder $query, ?string $category): void
    {
        if ($category === null) {
            return;
        }

        $categoryTypes = collect(ActivityType::cases())
            ->filter(fn (ActivityType $activityType): bool => $activityType->category() === $category)
            ->map(fn (ActivityType $activityType) => $activityType->value)
            ->values()
            ->all();

        if ($categoryTypes === []) {
            return;
        }

        $query->whereIn('type', $categoryTypes);
    }
}
