<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\Briefing;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * List briefings accessible to a workspace based on plan limits.
 */
final readonly class ListAccessibleBriefings
{
    /**
     * Get all briefings accessible to the workspace.
     *
     * @return Collection<int, Briefing>
     */
    public function handle(): Collection
    {
        $briefings = Briefing::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $briefings->values();
    }
}
