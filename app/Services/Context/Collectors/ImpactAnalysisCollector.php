<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\CodeIndex;
use App\Models\Repository;
use App\Models\Run;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\ValueObjects\ImpactedFile;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects files that reference symbols modified in the current PR.
 *
 * This collector searches the code index for cross-references to functions,
 * classes, and methods that were modified in the PR. It provides the AI
 * reviewer with context about potential impact across the codebase.
 */
final readonly class ImpactAnalysisCollector implements ContextCollector
{
    /**
     * Create a new ImpactAnalysisCollector instance.
     */
    public function __construct(
        private CodeSearchServiceContract $codeSearchService,
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'impact_analysis';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 75; // After SemanticCollector (80), which provides symbol data
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        // Verify repository has code indexing
        if (! $this->hasCodeIndex($repository)) {
            Log::debug('ImpactAnalysisCollector: Repository has no code index', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        // Require semantic data to extract modified symbols
        if ($bag->semantics === []) {
            Log::debug('ImpactAnalysisCollector: No semantic data available');

            return;
        }

        // Extract modified symbols from semantic analysis
        $modifiedSymbols = $this->extractModifiedSymbols($bag);

        if ($modifiedSymbols === []) {
            Log::debug('ImpactAnalysisCollector: No modified symbols found');

            return;
        }

        // Limit symbols to search
        $symbolsToSearch = array_slice($modifiedSymbols, 0, $this->maxSymbols());

        Log::debug('ImpactAnalysisCollector: Searching for references', [
            'symbols_count' => count($symbolsToSearch),
            'total_modified' => count($modifiedSymbols),
        ]);

        // Get files in the PR to exclude from results
        $prFiles = array_column($bag->files, 'filename');

        // Search for each symbol and collect impacted files
        $impactedFiles = $this->findImpactedFiles($repository, $symbolsToSearch, $prFiles, $run);

        if ($impactedFiles === []) {
            Log::debug('ImpactAnalysisCollector: No impacted files found');

            return;
        }

        // Convert to array format for the bag
        $bag->impactedFiles = array_map(
            fn (ImpactedFile $file): array => $file->toArray(),
            $impactedFiles
        );

        Log::info('ImpactAnalysisCollector: Found impacted files', [
            'repository' => $repository->full_name,
            'symbols_searched' => count($symbolsToSearch),
            'impacted_files' => count($impactedFiles),
        ]);
    }

    /**
     * Check if the repository has code indexing enabled.
     */
    private function hasCodeIndex(Repository $repository): bool
    {
        return CodeIndex::forRepository($repository)->exists();
    }

    /**
     * Extract modified symbols from semantic analysis.
     *
     * Compares semantic data with file patches to identify which symbols
     * were actually modified in this PR.
     *
     * @return array<int, array{name: string, type: string, file: string}>
     */
    private function extractModifiedSymbols(ContextBag $bag): array
    {
        $modifiedSymbols = [];

        foreach ($bag->semantics as $filename => $semanticData) {
            // Find the corresponding file in the PR
            $fileEntry = $this->findFileEntry($bag->files, $filename);

            if ($fileEntry === null) {
                continue;
            }

            if (! isset($fileEntry['patch'])) {
                continue;
            }

            // Parse the patch to find modified line ranges
            $modifiedLines = $this->parseModifiedLines($fileEntry['patch']);

            // Check functions
            $functions = $semanticData['functions'] ?? [];
            if (! is_array($functions)) {
                $functions = [];
            }

            foreach ($functions as $function) {
                if (! is_array($function)) {
                    continue;
                }

                $functionName = $function['name'] ?? null;
                if (! is_string($functionName)) {
                    continue;
                }

                if ($functionName === '') {
                    continue;
                }

                if ($this->symbolOverlapsLines($function, $modifiedLines)) {
                    $modifiedSymbols[] = [
                        'name' => $functionName,
                        'type' => 'function',
                        'file' => $filename,
                    ];
                }
            }

            // Check classes and their methods
            $classes = $semanticData['classes'] ?? [];
            if (! is_array($classes)) {
                $classes = [];
            }

            foreach ($classes as $class) {
                if (! is_array($class)) {
                    continue;
                }

                $className = $class['name'] ?? null;
                if (! is_string($className)) {
                    continue;
                }

                if ($className === '') {
                    continue;
                }

                if ($this->symbolOverlapsLines($class, $modifiedLines)) {
                    $modifiedSymbols[] = [
                        'name' => $className,
                        'type' => 'class',
                        'file' => $filename,
                    ];
                }

                // Check methods within the class
                $methods = $class['methods'] ?? [];
                if (! is_array($methods)) {
                    $methods = [];
                }

                foreach ($methods as $method) {
                    if (! is_array($method)) {
                        continue;
                    }

                    $methodName = $method['name'] ?? null;
                    if (! is_string($methodName)) {
                        continue;
                    }

                    if ($methodName === '') {
                        continue;
                    }

                    if ($this->symbolOverlapsLines($method, $modifiedLines)) {
                        $modifiedSymbols[] = [
                            'name' => $methodName,
                            'type' => 'method',
                            'file' => $filename,
                        ];
                    }
                }
            }
        }

        return $modifiedSymbols;
    }

    /**
     * Find the file entry in the bag's files array.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}|null
     */
    private function findFileEntry(array $files, string $filename): ?array
    {
        foreach ($files as $file) {
            if ($file['filename'] === $filename) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Parse a unified diff patch to extract modified line numbers.
     *
     * @return array<int, int> Array of line numbers that were modified
     */
    private function parseModifiedLines(string $patch): array
    {
        $modifiedLines = [];
        $currentLine = 0;

        foreach (explode("\n", $patch) as $line) {
            // Match hunk header: @@ -start,count +start,count @@
            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches)) {
                $currentLine = (int) $matches[1];

                continue;
            }

            if ($line === '') {
                continue;
            }

            $firstChar = $line[0] ?? '';

            if ($firstChar === '+' && ! str_starts_with($line, '+++')) {
                // Added line
                $modifiedLines[] = $currentLine;
                $currentLine++;
            } elseif ($firstChar === '-' && ! str_starts_with($line, '---')) {
                // Deleted line - don't increment currentLine
                continue;
            } elseif ($firstChar === ' ' || $firstChar === '\\') {
                // Context line or "no newline" marker
                if ($firstChar === ' ') {
                    $currentLine++;
                }
            }
        }

        return $modifiedLines;
    }

    /**
     * Check if a symbol's line range overlaps with modified lines.
     *
     * @param  array<int|string, mixed>  $symbol
     * @param  array<int, int>  $modifiedLines
     */
    private function symbolOverlapsLines(array $symbol, array $modifiedLines): bool
    {
        $lineStart = $symbol['line_start'] ?? null;
        $lineEnd = $symbol['line_end'] ?? $lineStart;

        if ($lineStart === null) {
            return false;
        }

        return array_any($modifiedLines, fn (int $line): bool => $line >= $lineStart && $line <= $lineEnd);
    }

    /**
     * Find files that reference the modified symbols.
     *
     * @param  array<int, array{name: string, type: string, file: string}>  $symbols
     * @param  array<int, string>  $excludeFiles
     * @return array<int, ImpactedFile>
     */
    private function findImpactedFiles(Repository $repository, array $symbols, array $excludeFiles, Run $run): array
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

                    // Skip files already in the PR
                    if (in_array($filePath, $excludeFiles, true)) {
                        continue;
                    }

                    // Skip if score is too low
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
                        // Increment match count and use highest score
                        $candidateFiles[$key]['match_count']++;
                        $candidateFiles[$key]['score'] = max($candidateFiles[$key]['score'], $score);
                    }
                }
            }
        }

        // Sort by match count (descending), then score (descending)
        usort($candidateFiles, function (array $a, array $b): int {
            if ($a['match_count'] !== $b['match_count']) {
                return $b['match_count'] <=> $a['match_count'];
            }

            return $b['score'] <=> $a['score'];
        });

        // Limit to max files
        $candidateFiles = array_slice($candidateFiles, 0, $this->maxFiles());

        // Fetch full file contents for the top candidates
        return $this->fetchFileContents($repository, $candidateFiles, $run);
    }

    /**
     * Build search patterns for a symbol.
     *
     * @param  array{name: string, type: string, file: string}  $symbol
     * @return array<string, string> pattern => match_type
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
     * Fetch full file contents for impacted files.
     *
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

                if ($content !== null) {
                    $impactedFiles[] = new ImpactedFile(
                        filePath: $candidate['file_path'],
                        content: $content,
                        matchedSymbol: $candidate['symbol'],
                        matchType: $candidate['match_type'],
                        score: $candidate['score'],
                        matchCount: $candidate['match_count'],
                    );
                }
            } catch (Throwable $e) {
                Log::debug('ImpactAnalysisCollector: Failed to fetch file', [
                    'file' => $candidate['file_path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $impactedFiles;
    }

    /**
     * Fetch file content from GitHub.
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

        // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
        if (! is_array($response)) {
            return null;
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
     * Get max symbols config value.
     */
    private function maxSymbols(): int
    {
        return (int) config('reviews.impact_analysis.max_symbols', 25);
    }

    /**
     * Get max files config value.
     */
    private function maxFiles(): int
    {
        return (int) config('reviews.impact_analysis.max_files', 20);
    }

    /**
     * Get max file size config value.
     */
    private function maxFileSize(): int
    {
        return (int) config('reviews.impact_analysis.max_file_size', 50000);
    }

    /**
     * Get search limit per symbol config value.
     */
    private function searchLimitPerSymbol(): int
    {
        return (int) config('reviews.impact_analysis.search_limit_per_symbol', 50);
    }

    /**
     * Get min relevance score config value.
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
