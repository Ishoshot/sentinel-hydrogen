<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

/**
 * Truncates full file contents to fit within a token budget.
 */
final readonly class FileContentsTruncator
{
    private const float RATIO_SINGLE = 0.20;

    private const string LARGE_SUFFIX = "\n... [truncated - file too large]";

    private const string LIMIT_SUFFIX = "\n... [truncated - token limit]";

    /**
     * Create a new FileContentsTruncator instance.
     */
    public function __construct(
        private AbstractTokenTruncator $tokenTruncator,
    ) {}

    /**
     * Truncate full file contents to fit within a token budget.
     *
     * @param  array<string, string>  $fileContents
     * @return array<string, string>
     */
    public function truncate(array $fileContents, int $maxTokens): array
    {
        if ($fileContents === []) {
            return $fileContents;
        }

        $totalTokens = 0;
        $result = [];
        $maxTokensPerFile = (int) ($maxTokens * self::RATIO_SINGLE);

        foreach ($fileContents as $path => $content) {
            [$content, $contentTokens] = $this->truncateToSingleLimit($content, $maxTokensPerFile);

            if ($totalTokens + $contentTokens > $maxTokens) {
                $truncatedContent = $this->truncateToRemainingBudget($content, $maxTokens - $totalTokens);
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
     * @return array{0: string, 1: int}
     */
    private function truncateToSingleLimit(string $content, int $maxTokensPerFile): array
    {
        $contentTokens = $this->tokenTruncator->estimateTokens($content);

        if ($contentTokens > $maxTokensPerFile) {
            $content = $this->tokenTruncator->truncateText(
                $content,
                $maxTokensPerFile,
                self::LARGE_SUFFIX
            );
            $contentTokens = $maxTokensPerFile;
        }

        return [$content, $contentTokens];
    }

    /**
     * Truncate content using only the remaining token budget.
     */
    private function truncateToRemainingBudget(string $content, int $remainingTokens): ?string
    {
        if ($remainingTokens <= AbstractTokenTruncator::MIN_SECTION_TOKENS) {
            return null;
        }

        return $this->tokenTruncator->truncateText(
            $content,
            $remainingTokens,
            self::LIMIT_SUFFIX
        );
    }
}
