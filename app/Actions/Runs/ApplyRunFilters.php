<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

final class ApplyRunFilters
{
    /**
     * @param  Builder<Run>  $query
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters, Workspace $workspace): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['repository_id'])) {
            $repository = Repository::query()
                ->where('id', $filters['repository_id'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($repository !== null) {
                $query->where('repository_id', $repository->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (isset($filters['risk_level'])) {
            $query->whereJsonContains('metadata->review_summary->risk_level', $filters['risk_level']);
        }

        if (isset($filters['author'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->whereJsonContains('metadata->author->login', $filters['author'])
                    ->orWhere('metadata->sender_login', $filters['author']);
            });
        }

        if (isset($filters['from_date']) && is_string($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date']) && is_string($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['search']) && is_string($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $query) use ($search): void {
                $query->where('pr_title', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('metadata->pull_request_title', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('metadata->sender_login', 'like', sprintf('%%%s%%', $search))
                    ->orWhereHas('repository', function (Builder $repositoryQuery) use ($search): void {
                        $repositoryQuery->where('name', 'like', sprintf('%%%s%%', $search))
                            ->orWhere('full_name', 'like', sprintf('%%%s%%', $search));
                    });
            });
        }
    }

    /**
     * @param  Builder<Run>  $query
     */
    public function applySorting(Builder $query, string $sortBy, string $sortOrder): void
    {
        if ($sortBy === 'findings_count') {
            $query->orderBy('findings_count', $sortOrder);

            return;
        }

        $query->orderBy($sortBy, $sortOrder);
    }
}
