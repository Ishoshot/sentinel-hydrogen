<?php

declare(strict_types=1);

namespace App\Services\Commands\Builders;

use App\Models\CodeIndex;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\CommandPathRules;
use BackedEnum;
use Illuminate\Support\Facades\Log;

final readonly class IssueRetrievalContextBuilder
{
    private const int MAX_INTENTS = 4;

    private const int SEARCH_LIMIT = 15;

    private const int MAX_SEARCH_MATCHES = 8;

    private const int MAX_SYMBOLS = 5;

    private const int MAX_SYMBOL_MATCHES = 8;

    private const int MAX_PATH_PATTERNS = 4;

    private const int MAX_PATH_MATCHES = 10;

    private const int MAX_CONTEXT_CHARS = 4500;

    /**
     * Create a new IssueRetrievalContextBuilder instance.
     */
    public function __construct(private CodeSearchServiceContract $codeSearchService) {}

    /**
     * Build token-bounded issue retrieval context.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules, ?string $issueContext): ?string
    {
        if (! is_string($issueContext) || $issueContext === '') {
            Log::debug('Issue retrieval context skipped: missing issue context', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
            ]);

            return null;
        }

        $repository = $commandRun->repository;
        if ($repository === null) {
            Log::debug('Issue retrieval context skipped: missing repository relation', [
                'command_run_id' => $commandRun->id,
            ]);

            return null;
        }

        $intents = $this->buildSearchIntents($commandRun->query, $issueContext);
        if ($intents === []) {
            Log::debug('Issue retrieval context skipped: no retrieval intents derived', [
                'command_run_id' => $commandRun->id,
                'repository' => $repository->full_name,
            ]);

            return null;
        }

        $searchMatches = $this->collectSearchMatches($repository, $intents, $pathRules);
        $symbolMatches = $this->collectSymbolMatches($repository, $commandRun->query, $issueContext, $pathRules);
        $pathMatches = $this->collectPathMatches($repository, $intents, $pathRules);

        if ($searchMatches === [] && $symbolMatches === [] && $pathMatches === []) {
            Log::debug('Issue retrieval context skipped: no matches found', [
                'command_run_id' => $commandRun->id,
                'repository' => $repository->full_name,
                'intents_count' => count($intents),
            ]);

            return null;
        }

        $sections = ['## Issue Retrieval Context'];

        $sections[] = "### Retrieval Intents\n".implode("\n", array_map(
            static fn (string $intent): string => '- '.$intent,
            $intents
        ));

        if ($searchMatches !== []) {
            $sections[] = "### Top Code Matches\n".implode("\n", array_map(
                static fn (array $match, int $index): string => sprintf(
                    '%d. `%s` (score %.2f, via `%s`) — %s',
                    $index + 1,
                    $match['file_path'],
                    $match['score'],
                    $match['intent'],
                    $match['snippet']
                ),
                $searchMatches,
                array_keys($searchMatches)
            ));
        }

        if ($symbolMatches !== []) {
            $sections[] = "### Symbol Hits\n".implode("\n", array_map(
                static fn (array $match): string => sprintf(
                    '- `%s` in `%s` (%s)',
                    $match['symbol_name'],
                    $match['file_path'],
                    $match['chunk_type']
                ),
                $symbolMatches
            ));
        }

        if ($pathMatches !== []) {
            $sections[] = "### File Path Discovery\n".implode("\n", array_map(
                static fn (array $match): string => sprintf('- `%s` (matched `%s`)', $match['file_path'], $match['pattern']),
                $pathMatches
            ));
        }

        $context = implode("\n\n", $sections)."\n";

        if (mb_strlen($context) <= self::MAX_CONTEXT_CHARS) {
            Log::debug('Issue retrieval context prepared', [
                'command_run_id' => $commandRun->id,
                'repository' => $repository->full_name,
                'intents_count' => count($intents),
                'search_matches' => count($searchMatches),
                'symbol_matches' => count($symbolMatches),
                'path_matches' => count($pathMatches),
                'context_chars' => mb_strlen($context),
                'truncated' => false,
            ]);

            return $context;
        }

        $truncated = mb_substr($context, 0, self::MAX_CONTEXT_CHARS)."\n... (retrieval context truncated)\n";

        Log::debug('Issue retrieval context prepared', [
            'command_run_id' => $commandRun->id,
            'repository' => $repository->full_name,
            'intents_count' => count($intents),
            'search_matches' => count($searchMatches),
            'symbol_matches' => count($symbolMatches),
            'path_matches' => count($pathMatches),
            'context_chars' => mb_strlen($truncated),
            'truncated' => true,
        ]);

        return $truncated;
    }

    /**
     * @return array<int, string>
     */
    private function buildSearchIntents(string $query, string $issueContext): array
    {
        $intents = [];

        $primaryQuery = mb_trim($query);
        if ($primaryQuery !== '') {
            $intents[] = $primaryQuery;
        }

        if (preg_match('/\*\*Title\*\*:\s*(.+)/', $issueContext, $matches) === 1) {
            $titleIntent = mb_trim($matches[1]);
            if ($titleIntent !== '') {
                $intents[] = $titleIntent;
            }
        }

        $keywords = $this->extractKeywords($issueContext.' '.$query);
        if ($keywords !== []) {
            $intents[] = implode(' ', array_slice($keywords, 0, 6));
        }

        if ($primaryQuery !== '' && $keywords !== []) {
            $intents[] = $primaryQuery.' '.implode(' ', array_slice($keywords, 0, 3));
        }

        $intents = array_values(array_unique(array_filter(
            $intents,
            static fn (string $intent): bool => $intent !== ''
        )));

        return array_slice($intents, 0, self::MAX_INTENTS);
    }

    /**
     * @param  array<int, string>  $intents
     * @return array<int, array{file_path: string, score: float, intent: string, snippet: string}>
     */
    private function collectSearchMatches(Repository $repository, array $intents, CommandPathRules $pathRules): array
    {
        $matches = [];

        foreach ($intents as $intent) {
            $results = $this->codeSearchService->search($repository, $intent, self::SEARCH_LIMIT);

            foreach ($results as $result) {
                $filePath = $result['file_path'];
                $shouldSkip = $filePath === '' || ! $pathRules->shouldIncludePath($filePath);
                if ($shouldSkip) {
                    continue;
                }

                $snippet = $result['content'];
                $sanitized = $pathRules->sanitizeContentForPath($filePath, $snippet);
                $snippet = $this->compactSnippet($sanitized);

                $score = $result['score'];
                $current = $matches[$filePath] ?? null;

                if ($current !== null && $current['score'] >= $score) {
                    continue;
                }

                $matches[$filePath] = [
                    'file_path' => $filePath,
                    'score' => $score,
                    'intent' => $intent,
                    'snippet' => $snippet,
                ];
            }
        }

        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, self::MAX_SEARCH_MATCHES);
    }

    /**
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: string}>
     */
    private function collectSymbolMatches(
        Repository $repository,
        string $query,
        string $issueContext,
        CommandPathRules $pathRules
    ): array {
        $symbols = array_slice($this->extractSymbolCandidates($query.' '.$issueContext), 0, self::MAX_SYMBOLS);
        if ($symbols === []) {
            return [];
        }

        $matches = [];

        foreach ($symbols as $symbol) {
            $results = $this->codeSearchService->findSymbol($repository, $symbol, 4);

            foreach ($results as $result) {
                $filePath = $result['file_path'];
                $symbolName = $result['symbol_name'];
                $chunkType = $result['chunk_type'];
                $chunkTypeValue = $chunkType instanceof BackedEnum ? (string) $chunkType->value : (string) $chunkType;
                $shouldSkip = $filePath === '' || $symbolName === '' || ! $pathRules->shouldIncludePath($filePath);
                if ($shouldSkip) {
                    continue;
                }

                $key = $filePath.'|'.$symbolName.'|'.$chunkTypeValue;
                if (isset($matches[$key])) {
                    continue;
                }

                $matches[$key] = [
                    'file_path' => $filePath,
                    'symbol_name' => $symbolName,
                    'chunk_type' => $chunkTypeValue,
                ];
            }
        }

        return array_slice(array_values($matches), 0, self::MAX_SYMBOL_MATCHES);
    }

    /**
     * @param  array<int, string>  $intents
     * @return array<int, array{file_path: string, pattern: string}>
     */
    private function collectPathMatches(Repository $repository, array $intents, CommandPathRules $pathRules): array
    {
        $patterns = array_slice($this->extractPathPatterns($intents), 0, self::MAX_PATH_PATTERNS);

        if ($patterns === []) {
            return [];
        }

        $matches = [];
        foreach ($patterns as $pattern) {
            $paths = CodeIndex::query()
                ->where('repository_id', $repository->id)
                ->whereRaw('LOWER(file_path) LIKE ?', ['%'.mb_strtolower($pattern).'%'])
                ->select('file_path')
                ->distinct()
                ->orderBy('file_path')
                ->limit(6)
                ->pluck('file_path');

            foreach ($paths as $path) {
                $shouldSkip = ! is_string($path) || ! $pathRules->shouldIncludePath($path) || isset($matches[$path]);
                if ($shouldSkip) {
                    continue;
                }

                $matches[$path] = [
                    'file_path' => $path,
                    'pattern' => $pattern,
                ];
            }
        }

        return array_slice(array_values($matches), 0, self::MAX_PATH_MATCHES);
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $normalized = mb_strtolower($text);

        preg_match_all('/[a-z][a-z0-9_]{3,}/', $normalized, $matches);
        $tokens = $matches[0];

        $stopwords = [
            'issue', 'context', 'title', 'state', 'labels', 'assignees', 'milestone', 'description',
            'recent', 'comments', 'command', 'request', 'query', 'this', 'that', 'with', 'from',
            'your', 'when', 'what', 'where', 'should', 'could', 'would', 'into', 'about', 'have',
        ];

        $filtered = array_filter($tokens, static fn (string $token): bool => ! in_array($token, $stopwords, true));
        $counts = array_count_values($filtered);
        arsort($counts);

        return array_keys($counts);
    }

    /**
     * @return array<int, string>
     */
    private function extractSymbolCandidates(string $text): array
    {
        preg_match_all('/\b(?:[A-Z]\w{2,}|[a-z_]\w{2,}\(\))\b/', $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * @param  array<int, string>  $intents
     * @return array<int, string>
     */
    private function extractPathPatterns(array $intents): array
    {
        $patterns = [];

        foreach ($intents as $intent) {
            preg_match_all('/[A-Za-z][A-Za-z0-9_\/.-]{2,}/', $intent, $matches);
            foreach ($matches[0] as $match) {
                $normalized = mb_strtolower(mb_trim($match, '.-_/'));
                $shouldSkip = $normalized === '' || mb_strlen($normalized) < 3;
                if ($shouldSkip) {
                    continue;
                }

                $patterns[] = $normalized;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Compact a snippet to a single short line for prompt inclusion.
     */
    private function compactSnippet(string $content): string
    {
        $compact = preg_replace('/\s+/', ' ', mb_trim($content)) ?? '';

        if (mb_strlen($compact) <= 200) {
            return $compact;
        }

        return mb_substr($compact, 0, 200).'...';
    }
}
