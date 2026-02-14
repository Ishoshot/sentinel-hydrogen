<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Models\Repository;
use App\Models\Run;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Context\ValueObjects\ImpactedFile;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Searches indexed code for symbol references and resolves impacted file contents.
 */
final readonly class ImpactedFileSearcher
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private CodeSearchServiceContract $codeSearchService,
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * @param  array<int, array{name: string, type: string, file: string}>  $symbols
     * @param  array<int, string>  $excludeFiles
     * @return array<int, ImpactedFile>
     */
    public function findImpactedFiles(Repository $repository, array $symbols, array $excludeFiles, Run $run): array
    {
        $candidateFiles = [];

        foreach ($symbols as $symbol) {
            $searchPatterns = $this->buildSearchPatterns($symbol);

            foreach ($searchPatterns as $pattern => $matchType) {
                $results = $this->codeSearchService->keywordSearch(
                    $repository,
                    $pattern,
                    $this->searchLimitPerSymbol()
                );

                foreach ($results as $result) {
                    $filePath = $result['file_path'];

                    if (in_array($filePath, $excludeFiles, true)) {
                        continue;
                    }

                    $score = (float) $result['score'];
                    if ($score < $this->minRelevanceScore()) {
                        continue;
                    }

                    $key = $filePath.':'.$symbol['name'];

                    if (! isset($candidateFiles[$key])) {
                        $candidateFiles[$key] = [
                            'file_path' => $filePath,
                            'symbol' => $symbol['name'],
                            'match_type' => $matchType,
                            'score' => $score,
                            'match_count' => 1,
                            'content' => $result['content'],
                        ];
                    } else {
                        $candidateFiles[$key]['match_count']++;
                        $candidateFiles[$key]['score'] = max($candidateFiles[$key]['score'], $score);
                    }
                }
            }
        }

        usort($candidateFiles, function (array $a, array $b): int {
            if ($a['match_count'] !== $b['match_count']) {
                return $b['match_count'] <=> $a['match_count'];
            }

            return $b['score'] <=> $a['score'];
        });

        $candidateFiles = array_slice($candidateFiles, 0, $this->maxFiles());

        return $this->fetchFileContents($repository, $candidateFiles, $run);
    }

    /**
     * @param  array{name: string, type: string, file: string}  $symbol
     * @return array<string, string>
     */
    private function buildSearchPatterns(array $symbol): array
    {
        $name = $symbol['name'];
        $type = $symbol['type'];

        return match ($type) {
            'function' => [
                $name.'(' => 'function_call',
            ],
            'class' => [
                'new '.$name => 'class_instantiation',
                'extends '.$name => 'extends',
                'implements '.$name => 'implements',
            ],
            'method' => [
                sprintf('->%s(', $name) => 'method_call',
                sprintf('::%s(', $name) => 'method_call',
            ],
            default => [
                $name => 'reference',
            ],
        };
    }

    /**
     * @param  array<int, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>  $candidates
     * @return array<int, ImpactedFile>
     */
    private function fetchFileContents(Repository $repository, array $candidates, Run $run): array
    {
        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            return [];
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            return [];
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;

        $metadata = $run->metadata ?? [];
        $headSha = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        $impactedFiles = [];

        foreach ($candidates as $candidate) {
            try {
                $content = $this->fetchFileContent(
                    $installationId,
                    $owner,
                    $repo,
                    $candidate['file_path'],
                    $headSha
                );

                if ($content === null) {
                    continue;
                }

                $impactedFiles[] = new ImpactedFile(
                    filePath: $candidate['file_path'],
                    content: $content,
                    matchedSymbol: $candidate['symbol'],
                    matchType: $candidate['match_type'],
                    score: $candidate['score'],
                    matchCount: $candidate['match_count'],
                );
            } catch (Throwable $throwable) {
                Log::debug('ImpactAnalysisCollector: Failed to fetch file', [
                    'file' => $candidate['file_path'],
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return $impactedFiles;
    }

    /**
     * Fetch and decode a file from GitHub for a specific reference.
     */
    private function fetchFileContent(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        ?string $ref
    ): ?string {
        $response = $this->gitHubApiService->getFileContents(
            $installationId,
            $owner,
            $repo,
            $path,
            $ref
        );

        if (is_string($response)) {
            return mb_strlen($response) <= $this->maxFileSize() ? $response : null;
        }

        $size = $response['size'] ?? 0;
        if (! is_int($size) || $size > $this->maxFileSize()) {
            return null;
        }

        $content = $response['content'] ?? null;
        $encoding = $response['encoding'] ?? 'base64';

        if (! is_string($content)) {
            return null;
        }

        if ($encoding === 'base64') {
            $decoded = base64_decode($content, true);

            return $decoded !== false ? $decoded : null;
        }

        return $content;
    }

    /**
     * Resolve the maximum number of impacted files to include.
     */
    private function maxFiles(): int
    {
        return (int) config('reviews.impact_analysis.max_files', 20);
    }

    /**
     * Resolve the maximum file size allowed for impacted file content.
     */
    private function maxFileSize(): int
    {
        return (int) config('reviews.impact_analysis.max_file_size', 50000);
    }

    /**
     * Resolve the per-symbol search result cap.
     */
    private function searchLimitPerSymbol(): int
    {
        return (int) config('reviews.impact_analysis.search_limit_per_symbol', 50);
    }

    /**
     * Resolve the minimum score required to keep a search result.
     */
    private function minRelevanceScore(): float
    {
        $value = config('reviews.impact_analysis.min_relevance_score', 0.3);

        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.3;
    }
}
