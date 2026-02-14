<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

/**
 * Merges keyword and semantic search results using weighted scoring.
 */
final readonly class HybridSearchResultMerger
{
    private const float KEYWORD_WEIGHT = 0.6;

    private const float SEMANTIC_WEIGHT = 0.4;

    /**
     * Merge keyword and semantic search results.
     *
     * @param  array<int, array<string, mixed>>  $keywordResults
     * @param  array<int, array<string, mixed>>  $semanticResults
     * @return array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}>
     */
    public function merge(array $keywordResults, array $semanticResults, int $limit): array
    {
        $merged = [];
        $seen = [];

        foreach ($keywordResults as $result) {
            $filePath = (string) ($result['file_path'] ?? '');
            $content = (string) ($result['content'] ?? '');
            $rawScore = $result['score'] ?? 0.0;
            $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;
            $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

            $key = $filePath.':'.hash('xxh128', $content);
            if (! isset($seen[$key])) {
                $merged[$key] = [
                    'file_path' => $filePath,
                    'content' => $content,
                    'keyword_score' => $score,
                    'semantic_score' => 0.0,
                    'metadata' => $metadata,
                ];
                $seen[$key] = true;
            }
        }

        foreach ($semanticResults as $result) {
            $filePath = (string) ($result['file_path'] ?? '');
            $content = (string) ($result['content'] ?? '');
            $rawScore = $result['score'] ?? 0.0;
            $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;
            $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

            $key = $filePath.':'.hash('xxh128', $content);
            if (isset($merged[$key])) {
                $merged[$key]['semantic_score'] = $score;
            } else {
                $merged[$key] = [
                    'file_path' => $filePath,
                    'content' => $content,
                    'keyword_score' => 0.0,
                    'semantic_score' => $score,
                    'metadata' => $metadata,
                ];
            }
        }

        /** @var array<string, array{file_path: string, content: string, keyword_score: float, semantic_score: float, metadata: array<string, mixed>}> $merged */
        $results = array_map(function (array $item): array {
            $keywordScore = $item['keyword_score'];
            $semanticScore = $item['semantic_score'];
            $combinedScore = ($keywordScore * self::KEYWORD_WEIGHT) + ($semanticScore * self::SEMANTIC_WEIGHT);

            $matchType = 'hybrid';
            if ($keywordScore > 0 && $semanticScore === 0.0) {
                $matchType = 'keyword';
            } elseif ($semanticScore > 0 && $keywordScore === 0.0) {
                $matchType = 'semantic';
            }

            return [
                'file_path' => $item['file_path'],
                'content' => $item['content'],
                'score' => $combinedScore,
                'match_type' => $matchType,
                'metadata' => $item['metadata'],
            ];
        }, $merged);

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }
}
