<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates diff patch payloads to stay within token budgets.
 */
final readonly class TokenLimitFilePatchTruncator
{
    /**
     * Create a new file patch truncator instance.
     */
    public function __construct(private AbstractTokenTruncator $tokenTruncator) {}

    /**
     * Set token counting context for the current truncation cycle.
     */
    public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->tokenTruncator->setContext($tokenCounterContext);
    }

    /**
     * Truncate individual file patches that exceed per-file and total limits.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    public function truncateFiles(array $files, int $maxTokensPerFile, int $maxTokensAllFiles): array
    {
        $totalTokens = 0;

        return array_map(function (array $file) use (&$totalTokens, $maxTokensPerFile, $maxTokensAllFiles): array {
            $patch = $file['patch'];

            if ($patch === null) {
                return $file;
            }

            $patchTokens = $this->tokenTruncator->estimateTokens($patch);

            if ($patchTokens > $maxTokensPerFile) {
                $file['patch'] = $this->tokenTruncator->truncateText($patch, $maxTokensPerFile, "\n... [truncated - file too large]");
                $patchTokens = $maxTokensPerFile;
            }

            if ($totalTokens + $patchTokens > $maxTokensAllFiles) {
                $remainingBudget = $maxTokensAllFiles - $totalTokens;
                if ($remainingBudget > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $file['patch'] = $this->tokenTruncator->truncateText($patch, $remainingBudget, "\n... [truncated - token limit]");
                    $patchTokens = $remainingBudget;
                } else {
                    $file['patch'] = '[patch omitted - token limit reached]';
                    $patchTokens = 50;
                }
            }

            $totalTokens += $patchTokens;

            return $file;
        }, $files);
    }

    /**
     * Aggressively trim patches when progressive truncation is required.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    public function aggressiveTruncateFiles(array $files): array
    {
        $filesWithPatches = 0;
        $maxFilesWithPatches = 15;

        return array_map(function (array $file) use (&$filesWithPatches, $maxFilesWithPatches): array {
            if ($file['patch'] !== null && $file['patch'] !== '') {
                $filesWithPatches++;

                if ($filesWithPatches > $maxFilesWithPatches) {
                    $file['patch'] = '[patch omitted - too many files]';
                } elseif (mb_strlen($file['patch']) > 2000) {
                    $file['patch'] = mb_substr($file['patch'], 0, 2000)."\n... [aggressively truncated]";
                }
            }

            return $file;
        }, $files);
    }
}
