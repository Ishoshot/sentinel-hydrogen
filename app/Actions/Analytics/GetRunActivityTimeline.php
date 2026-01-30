<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Get run activity timeline showing runs over time.
 */
final readonly class GetRunActivityTimeline
{
    /**
     * Get run activity timeline for a workspace.
     *
     * @return Collection<int, mixed>
     */
    public function handle(Workspace $workspace, int $days = 30): Collection
    {
        return $workspace->runs()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
