<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates code-centric context sections.
 */
final readonly class TokenLimitCodeSectionTruncator
{
    private const float RATIO_IMPACTED_FILE_SINGLE = 0.25;

    private const float RATIO_FILE_CONTENTS_SINGLE = 0.20;

    private const int IMPACTED_FILE_METADATA_TOKENS = 50;

    private const string IMPACTED_FILE_LARGE_SUFFIX = "\n... [truncated - impacted file too large]";

    private const string IMPACTED_FILE_LIMIT_SUFFIX = "\n... [truncated - token limit]";

    private const string FILE_CONTENT_LARGE_SUFFIX = "\n... [truncated - file too large]";

    private const string FILE_CONTENT_LIMIT_SUFFIX = "\n... [truncated - token limit]";

    /**
     * Create a new code section truncator instance.
     */
    public function __construct(
        private AbstractTokenTruncator $tokenTruncator,
        private TokenLimitSemanticDataTruncator $semanticDataTruncator,
    ) {}

    /**
     * Set token counting context for the current truncation cycle.
     */
    public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->tokenTruncator->setContext($tokenCounterContext);
    }

    /**
     * Truncate impacted files to fit within a token budget.
     *
     * @param  array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>  $impactedFiles
     * @return array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>
     */
    public function truncateImpactedFiles(array $impactedFiles, int $maxTokens): array
    {
        if ($impactedFiles === []) {
            return $impactedFiles;
        }

        $totalTokens = 0;
        $result = [];
        $maxTokensPerFile = (int) ($maxTokens * self::RATIO_IMPACTED_FILE_SINGLE);

        foreach ($impactedFiles as $file) {
            [$file, $fileTokens] = $this->truncateImpactedFileToSingleLimit($file, $maxTokensPerFile);

            if ($this->exceedsBudget($totalTokens, $fileTokens, $maxTokens)) {
                $truncatedFile = $this->truncateImpactedFileToRemainingBudget($file, $maxTokens - $totalTokens);
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
     * Truncate full file contents to fit within a token budget.
     *
     * @param  array<string, string>  $fileContents
     * @return array<string, string>
     */
    public function truncateFileContents(array $fileContents, int $maxTokens): array
    {
        if ($fileContents === []) {
            return $fileContents;
        }

        $totalTokens = 0;
        $result = [];
        $maxTokensPerFile = (int) ($maxTokens * self::RATIO_FILE_CONTENTS_SINGLE);

        foreach ($fileContents as $path => $content) {
            [$content, $contentTokens] = $this->truncateFileContentToSingleLimit($content, $maxTokensPerFile);

            if ($this->exceedsBudget($totalTokens, $contentTokens, $maxTokens)) {
                $truncatedContent = $this->truncateFileContentToRemainingBudget($content, $maxTokens - $totalTokens);
                if ($truncatedContent !== null) {
                    $result[$path] = $truncatedContent;
                }
                break;
            }

            $result[$path] = $content;
            $totalTokens += $contentTokens;
        }

        return $result;
    }

    /**
     * Truncate semantic analysis data to fit within a token budget.
     *
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array<string, array<string, mixed>>
     */
    public function truncateSemantics(array $semantics, int $maxTokens): array
    {
        if ($semantics === []) {
            return $semantics;
        }

        $totalTokens = 0;
        $result = [];

        foreach ($semantics as $path => $data) {
            $dataTokens = $this->estimateSemanticDataTokens($data);

            if ($this->exceedsBudget($totalTokens, $dataTokens, $maxTokens)) {
                $remaining = $maxTokens - $totalTokens;

                if ($this->hasRemainingSectionBudget($remaining)) {
                    $truncatedData = $this->semanticDataTruncator->truncate($data, $remaining);
                    if ($truncatedData !== []) {
                        $result[$path] = $truncatedData;
                    }
                }

                break;
            }

            $result[$path] = $data;
            $totalTokens += $dataTokens;
        }

        return $result;
    }

    /**
     * @param  array{
     *     file_path: string,
     *     content: string,
     *     matched_symbol: string,
     *     match_type: string,
     *     score: float,
     *     match_count: int,
     *     reason: string
     * }  $file
     * @return array{
     *     0: array{
     *         file_path: string,
     *         content: string,
     *         matched_symbol: string,
     *         match_type: string,
     *         score: float,
     *         match_count: int,
     *         reason: string
     *     },
     *     1: int
     * }
     */
    private function truncateImpactedFileToSingleLimit(array $file, int $maxTokensPerFile): array
    {
        $fileTokens = $this->tokenTruncator->estimateTokens($file['content']) + self::IMPACTED_FILE_METADATA_TOKENS;

        if ($fileTokens > $maxTokensPerFile) {
            $file['content'] = $this->tokenTruncator->truncateText(
                $file['content'],
                $maxTokensPerFile - self::IMPACTED_FILE_METADATA_TOKENS,
                self::IMPACTED_FILE_LARGE_SUFFIX
            );
            $fileTokens = $maxTokensPerFile;
        }

        return [$file, $fileTokens];
    }

    /**
     * @param  array{
     *     file_path: string,
     *     content: string,
     *     matched_symbol: string,
     *     match_type: string,
     *     score: float,
     *     match_count: int,
     *     reason: string
     * }  $file
     * @return array{
     *     file_path: string,
     *     content: string,
     *     matched_symbol: string,
     *     match_type: string,
     *     score: float,
     *     match_count: int,
     *     reason: string
     * }|null
     */
    private function truncateImpactedFileToRemainingBudget(array $file, int $remainingTokens): ?array
    {
        if (! $this->hasRemainingSectionBudget($remainingTokens)) {
            return null;
        }

        $file['content'] = $this->tokenTruncator->truncateText(
            $file['content'],
            $remainingTokens - self::IMPACTED_FILE_METADATA_TOKENS,
            self::IMPACTED_FILE_LIMIT_SUFFIX
        );

        return $file;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function truncateFileContentToSingleLimit(string $content, int $maxTokensPerFile): array
    {
        $contentTokens = $this->tokenTruncator->estimateTokens($content);

        if ($contentTokens > $maxTokensPerFile) {
            $content = $this->tokenTruncator->truncateText(
                $content,
                $maxTokensPerFile,
                self::FILE_CONTENT_LARGE_SUFFIX
            );
            $contentTokens = $maxTokensPerFile;
        }

        return [$content, $contentTokens];
    }

    private function truncateFileContentToRemainingBudget(string $content, int $remainingTokens): ?string
    {
        if (! $this->hasRemainingSectionBudget($remainingTokens)) {
            return null;
        }

        return $this->tokenTruncator->truncateText(
            $content,
            $remainingTokens,
            self::FILE_CONTENT_LIMIT_SUFFIX
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function estimateSemanticDataTokens(array $data): int
    {
        return $this->tokenTruncator->estimateTokens(json_encode($data) ?: '');
    }

    private function exceedsBudget(int $currentTokens, int $nextItemTokens, int $budget): bool
    {
        return $currentTokens + $nextItemTokens > $budget;
    }

    private function hasRemainingSectionBudget(int $remainingTokens): bool
    {
        return $remainingTokens > AbstractTokenTruncator::MIN_SECTION_TOKENS;
    }
}
