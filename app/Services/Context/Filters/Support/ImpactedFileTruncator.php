<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

/**
 * Truncates impacted file content to fit within a token budget.
 */
final readonly class ImpactedFileTruncator
{
    private const float RATIO_SINGLE = 0.25;

    private const int METADATA_TOKENS = 50;

    private const string LARGE_SUFFIX = "\n... [truncated - impacted file too large]";

    private const string LIMIT_SUFFIX = "\n... [truncated - token limit]";

    public function __construct(
        private AbstractTokenTruncator $tokenTruncator,
    ) {}

    /**
     * Truncate impacted files to fit within a token budget.
     *
     * @param  array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>  $impactedFiles
     * @return array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>
     */
    public function truncate(array $impactedFiles, int $maxTokens): array
    {
        if ($impactedFiles === []) {
            return $impactedFiles;
        }

        $totalTokens = 0;
        $result = [];
        $maxTokensPerFile = (int) ($maxTokens * self::RATIO_SINGLE);

        foreach ($impactedFiles as $file) {
            [$file, $fileTokens] = $this->truncateToSingleLimit($file, $maxTokensPerFile);

            if ($totalTokens + $fileTokens > $maxTokens) {
                $truncatedFile = $this->truncateToRemainingBudget($file, $maxTokens - $totalTokens);
                if ($truncatedFile !== null) {
                    $result[] = $truncatedFile;
                }
                break;
            }

            $result[] = $file;
            $totalTokens += $fileTokens;
        }

        return $result;
    }

    /**
     * @param  array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}  $file
     * @return array{0: array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}, 1: int}
     */
    private function truncateToSingleLimit(array $file, int $maxTokensPerFile): array
    {
        $fileTokens = $this->tokenTruncator->estimateTokens($file['content']) + self::METADATA_TOKENS;

        if ($fileTokens > $maxTokensPerFile) {
            $file['content'] = $this->tokenTruncator->truncateText(
                $file['content'],
                $maxTokensPerFile - self::METADATA_TOKENS,
                self::LARGE_SUFFIX
            );
            $fileTokens = $maxTokensPerFile;
        }

        return [$file, $fileTokens];
    }

    /**
     * @param  array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}  $file
     * @return array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}|null
     */
    private function truncateToRemainingBudget(array $file, int $remainingTokens): ?array
    {
        if ($remainingTokens <= AbstractTokenTruncator::MIN_SECTION_TOKENS) {
            return null;
        }

        $file['content'] = $this->tokenTruncator->truncateText(
            $file['content'],
            $remainingTokens - self::METADATA_TOKENS,
            self::LIMIT_SUFFIX
        );

        return $file;
    }
}
