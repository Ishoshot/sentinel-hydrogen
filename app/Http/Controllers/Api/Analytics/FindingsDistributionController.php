<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Enums\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Get findings distribution by severity level.
 */
final class FindingsDistributionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace): JsonResponse
    {
        $distribution = $workspace->findings()
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

        return response()->json([
            'data' => $distribution,
        ]);
    }
}
