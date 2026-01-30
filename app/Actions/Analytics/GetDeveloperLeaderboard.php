<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Get developer leaderboard showing most active contributors.
 */
final readonly class GetDeveloperLeaderboard
{
    /**
     * Get developer leaderboard for a workspace.
     *
     * @return Collection<int, array{id: int, name: string|null, email: string|null, avatar_url: string|null, runs_count: int, successful_runs: int, avg_duration: int|null}>
     */
    public function handle(Workspace $workspace, int $days = 30, int $limit = 10): Collection
    {
        return $workspace->runs()
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.avatar_url',
                DB::raw('COUNT(runs.id) as runs_count'),
                DB::raw("SUM(CASE WHEN runs.status = 'completed' THEN 1 ELSE 0 END) as successful_runs"),
                DB::raw('AVG(runs.duration_seconds) as avg_duration'),
            )
            ->join('users', 'runs.initiated_by_id', '=', 'users.id')
            ->where('runs.created_at', '>=', now()->subDays($days))
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar_url')
            ->orderByDesc('runs_count')
            ->limit($limit)
            ->get()
            ->map(static function (Run $run): array {
                /** @var int|string $runsCount */
                $runsCount = $run->getAttribute('runs_count');
                /** @var int|string $successfulRuns */
                $successfulRuns = $run->getAttribute('successful_runs');
                /** @var float|int|string|null $avgDuration */
                $avgDuration = $run->getAttribute('avg_duration');
                /** @var string|null $name */
                $name = $run->getAttribute('name');
                /** @var string|null $email */
                $email = $run->getAttribute('email');
                /** @var string|null $avatarUrl */
                $avatarUrl = $run->getAttribute('avatar_url');

                return [
                    'id' => $run->id,
                    'name' => $name,
                    'email' => $email,
                    'avatar_url' => $avatarUrl,
                    'runs_count' => (int) $runsCount,
                    'successful_runs' => (int) $successfulRuns,
                    'avg_duration' => $avgDuration !== null ? (int) $avgDuration : null,
                ];
            });
    }
}
