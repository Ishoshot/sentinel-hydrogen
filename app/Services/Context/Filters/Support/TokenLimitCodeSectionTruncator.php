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

    /**
     * Create a new code section truncator instance.
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
            $contentTokens = $this->tokenTruncator->estimateTokens($file['content']);
            $metadataTokens = 50;
            $fileTokens = $contentTokens + $metadataTokens;

            if ($fileTokens > $maxTokensPerFile) {
                $file['content'] = $this->tokenTruncator->truncateText(
                    $file['content'],
                    $maxTokensPerFile - $metadataTokens,
                    "\n... [truncated - impacted file too large]"
                );
                $fileTokens = $maxTokensPerFile;
            }

            if ($totalTokens + $fileTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $file['content'] = $this->tokenTruncator->truncateText(
                        $file['content'],
                        $remaining - $metadataTokens,
                        "\n... [truncated - token limit]"
                    );
                    $result[] = $file;
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
            $contentTokens = $this->tokenTruncator->estimateTokens($content);

            if ($contentTokens > $maxTokensPerFile) {
                $content = $this->tokenTruncator->truncateText(
                    $content,
                    $maxTokensPerFile,
                    "\n... [truncated - file too large]"
                );
                $contentTokens = $maxTokensPerFile;
            }

            if ($totalTokens + $contentTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $result[$path] = $this->tokenTruncator->truncateText(
                        $content,
                        $remaining,
                        "\n... [truncated - token limit]"
                    );
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
            $dataTokens = $this->tokenTruncator->estimateTokens(json_encode($data) ?: '');

            if ($totalTokens + $dataTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;

                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $truncatedData = $this->truncateSemanticData($data, $remaining);
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function truncateSemanticData(array $data, int $maxTokens): array
    {
        $result = [];

        if (isset($data['language'])) {
            $result['language'] = $data['language'];
        }

        if (isset($data['functions']) && is_array($data['functions'])) {
            $result['functions'] = array_slice($data['functions'], 0, 5);
        }

        if (isset($data['classes']) && is_array($data['classes'])) {
            $classes = array_slice($data['classes'], 0, 3);
            foreach ($classes as &$class) {
                if (isset($class['methods']) && is_array($class['methods'])) {
                    $class['methods'] = array_slice($class['methods'], 0, 5);
                }
            }

            $result['classes'] = $classes;
        }

        if (isset($data['imports']) && is_array($data['imports'])) {
            $result['imports'] = array_slice($data['imports'], 0, 5);
        }

        if ($this->tokenTruncator->estimateTokens(json_encode($result) ?: '') <= $maxTokens) {
            return $result;
        }

        return [
            'language' => $data['language'] ?? 'unknown',
            'functions' => array_slice($data['functions'] ?? [], 0, 2),
            'classes' => array_slice($data['classes'] ?? [], 0, 1),
        ];
    }
}
