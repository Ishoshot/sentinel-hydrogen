<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Collects, merges, and ranks impacted-file candidates from code search results.
 */
final readonly class ImpactedFileCandidateCollector
{
    /**
     * @param  array<string, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>  $candidates
     * @param  array<int, array{file_path: string, content: string, score: float, metadata?: array<string, mixed>}>  $results
     * @param  array{name: string, type: string, file: string}  $symbol
     * @param  array<int, string>  $excludeFiles
     * @return array<string, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>
     */
    public function collect(
        array $candidates,
        array $results,
        array $symbol,
        array $excludeFiles,
        string $matchType,
        float $minRelevanceScore
    ): array {
        foreach ($results as $result) {
            $filePath = $result['file_path'];

            if (in_array($filePath, $excludeFiles, true)) {
                continue;
            }

            $score = (float) $result['score'];
            if ($score < $minRelevanceScore) {
                continue;
            }

            $key = $filePath.':'.$symbol['name'];

            if (! isset($candidates[$key])) {
                $candidates[$key] = [
                    'file_path' => $filePath,
                    'symbol' => $symbol['name'],
                    'match_type' => $matchType,
                    'score' => $score,
                    'match_count' => 1,
                    'content' => $result['content'],
                ];
            } else {
                $candidates[$key]['match_count']++;
                $candidates[$key]['score'] = max($candidates[$key]['score'], $score);
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>  $candidates
     * @return array<int, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>
     */
    public function rank(array $candidates, int $maxFiles): array
    {
        $rankedCandidates = array_values($candidates);

        usort($rankedCandidates, function (array $a, array $b): int {
            if ($a['match_count'] !== $b['match_count']) {
                return $b['match_count'] <=> $a['match_count'];
            }

            return $b['score'] <=> $a['score'];
        });

        return array_slice($rankedCandidates, 0, $maxFiles);
    }
}
