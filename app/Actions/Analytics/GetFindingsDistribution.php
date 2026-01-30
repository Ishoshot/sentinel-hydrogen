<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Get findings distribution by severity level.
 */
final readonly class GetFindingsDistribution
{
    /**
     * Get findings distribution for a workspace.
     *
     * @return Collection<int, array{severity: string, count: int}>
     */
    public function handle(Workspace $workspace): Collection
    {
        return $workspace->findings()
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->groupBy('severity')
            ->get()
            ->map(static function (Finding $finding): array {
                /** @var SentinelConfigSeverity|string|null $severity */
                $severity = $finding->getAttribute('severity');
                /** @var int|string $count */
                $count = $finding->getAttribute('count');

                return [
                    'severity' => $severity instanceof SentinelConfigSeverity ? $severity->value : (string) $severity,
                    'count' => (int) $count,
                ];
            });
    }
}
