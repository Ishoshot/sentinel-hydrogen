<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Finding;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Get finding resolution rate showing time to annotation.
 *
 * Measures how quickly findings get annotated (commented/resolved) on GitHub.
 */
final class ResolutionRateController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        $findings = $workspace->findings()
            ->with('annotations')
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $resolution = $findings
            ->groupBy(static fn (Finding $finding): string => $finding->created_at->toDateString())
            ->map(static function (Collection $dayFindings, string $date): array {
                /** @var Collection<int, Finding> $dayFindings */
                $totalFindings = $dayFindings->count();
                $annotatedFindings = $dayFindings->filter(static fn (Finding $finding): bool => $finding->annotations->isNotEmpty())->count();
                $annotationRate = $totalFindings > 0 ? round(($annotatedFindings / $totalFindings) * 100, 2) : 0;

                $timesToAnnotation = [];

                foreach ($dayFindings as $finding) {
                    if ($finding->annotations->isEmpty()) {
                        continue;
                    }

                    $firstAnnotation = $finding->annotations->sortBy('created_at')->first();

                    if ($firstAnnotation === null) {
                        continue;
                    }

                    $timesToAnnotation[] = $firstAnnotation->created_at->diffInSeconds($finding->created_at);
                }

                $avgTime = $timesToAnnotation !== []
                    ? (int) round(array_sum($timesToAnnotation) / count($timesToAnnotation))
                    : null;

                return [
                    'date' => $date,
                    'total_findings' => $totalFindings,
                    'annotated_findings' => $annotatedFindings,
                    'annotation_rate' => $annotationRate,
                    'avg_time_to_annotation_seconds' => $avgTime,
                ];
            })
            ->sortBy('date')
            ->values();

        return response()->json([
            'data' => $resolution,
        ]);
    }
}
