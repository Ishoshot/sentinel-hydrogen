<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Get developer leaderboard showing most active contributors.
 */
final class DeveloperLeaderboardController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $limit = (int) $request->query('limit', 10);

        $leaderboard = $workspace->runs()
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

        return response()->json([
            'data' => $leaderboard,
        ]);
    }
}
