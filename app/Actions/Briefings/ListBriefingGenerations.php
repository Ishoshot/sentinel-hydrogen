<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingGenerationStatusSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * List briefing generations with filtering, search, and sorting.
 */
final class ListBriefingGenerations
{
    /**
     * List briefing generations for the workspace.
     *
     * @return LengthAwarePaginator<int, BriefingGeneration>
     */
    public function handle(
        Workspace $workspace,
        ?string $search = null,
        ?BriefingGenerationStatusSet $statuses = null,
        ?int $briefingId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->with(['briefing', 'generatedBy']);

        $this->applySearch($query, $search);
        $this->applyFilters($query, $statuses, $briefingId, $dateFrom, $dateTo);
        $this->applySorting($query, $sort, $direction);

        return $query->paginate($perPage);
    }

    /**
     * Apply search filter.
     *
     * @param  Builder<BriefingGeneration>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $query->whereHas('briefing', function (Builder $q) use ($search): void {
            $q->where('title', 'like', sprintf('%%%s%%', $search));
        });
    }

    /**
     * Apply filters.
     *
     * @param  Builder<BriefingGeneration>  $query
     */
    private function applyFilters(
        Builder $query,
        ?BriefingGenerationStatusSet $statuses,
        ?int $briefingId,
        ?string $dateFrom,
        ?string $dateTo,
    ): void {
        if ($statuses instanceof BriefingGenerationStatusSet && ! $statuses->isEmpty()) {
            $query->whereIn('status', $statuses->toArray());
        }

        if ($briefingId !== null) {
            $query->where('briefing_id', $briefingId);
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }

    /**
     * Apply sorting.
     *
     * @param  Builder<BriefingGeneration>  $query
     */
    private function applySorting(Builder $query, string $sort, string $direction): void
    {
        $query->orderBy($sort, $direction);
    }
}
