<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Orchestrates truncation of code-centric context sections.
 */
final readonly class TokenLimitCodeSectionTruncator
{
    /**
     * Create a new code section truncator instance.
     */
    public function __construct(
        private AbstractTokenTruncator $tokenTruncator,
        private TokenLimitSemanticDataTruncator $semanticDataTruncator,
        private ImpactedFileTruncator $impactedFileTruncator,
        private FileContentsTruncator $fileContentsTruncator,
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
        return $this->impactedFileTruncator->truncate($impactedFiles, $maxTokens);
    }

    /**
     * Truncate full file contents to fit within a token budget.
     *
     * @param  array<string, string>  $fileContents
     * @return array<string, string>
     */
    public function truncateFileContents(array $fileContents, int $maxTokens): array
    {
        return $this->fileContentsTruncator->truncate($fileContents, $maxTokens);
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
            $dataTokens = $this->tokenTruncator->estimateTokens(json_encode($data) ?: '');

            if ($totalTokens + $dataTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;

                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
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
}
