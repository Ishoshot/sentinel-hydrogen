<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * List all available plans.
 */
final class ListPlans
{
    /**
     * Get all plans ordered by price and tier.
     *
     * @return Collection<int, Plan>
     */
    public function handle(): Collection
    {
        return Plan::query()
            ->orderBy('price_monthly')
            ->orderBy('tier')
            ->get();
    }
}
