<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard\Builders;

use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Models\Run;
use Illuminate\Database\Eloquent\Builder;

final readonly class AdminDashboardRunQueryBuilder
{
    /**
     * @return Builder<Run>
     */
    public function buildBase(AdminDashboardFilters $filters): Builder
    {
        return $this->applyTo(
            query: Run::query(),
            filters: $filters,
            withDateRange: false,
        );
    }

    /**
     * @return Builder<Run>
     */
    public function buildForRange(AdminDashboardFilters $filters): Builder
    {
        return $this->applyTo(
            query: Run::query(),
            filters: $filters,
            withDateRange: true,
        );
    }

    /**
     * @param  Builder<Run>  $query
     * @return Builder<Run>
     */
    public function applyTo(
        Builder $query,
        AdminDashboardFilters $filters,
        bool $withDateRange = true,
    ): Builder {
        $query
            ->when($filters->workspaceId !== null, function (Builder $builder) use ($filters): void {
                $builder->where('workspace_id', $filters->workspaceId);
            })
            ->when($filters->planTier !== null, function (Builder $builder) use ($filters): void {
                $builder->whereHas('workspace.plan', function (Builder $planQuery) use ($filters): void {
                    $planQuery->where('tier', $filters->planTier?->value);
                });
            })
            ->when($filters->runStatus !== null, function (Builder $builder) use ($filters): void {
                $builder->where('status', $filters->runStatus?->value);
            });

        if ($withDateRange) {
            $query->whereBetween('created_at', [
                $filters->startDate,
                $filters->endDate,
            ]);
        }

        return $query;
    }
}
