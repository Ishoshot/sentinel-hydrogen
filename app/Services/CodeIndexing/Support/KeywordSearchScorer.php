<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

use App\Models\CodeIndex;

/**
 * Extracts search terms from queries, calculates keyword relevance scores,
 * and extracts content snippets around matches.
 */
final readonly class KeywordSearchScorer
{
    /** @var array<string> */
    private const array STOP_WORDS = ['the', 'a', 'an', 'is', 'are', 'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or'];

    private const int SNIPPET_MAX_LENGTH = 500;

    private const int SNIPPET_CONTEXT_BEFORE = 100;

    /**
     * Extract search terms from a query string.
     *
     * @return array<string>
     */
    public function extractTerms(string $query): array
    {
        $terms = preg_split('/[\s,.:;()\[\]{}]+/', $query);

        if ($terms === false) {
            return [];
        }

        return array_values(array_filter($terms, function (string $term): bool {
            $term = mb_strtolower(mb_trim($term));

            return mb_strlen($term) >= 2 && ! in_array($term, self::STOP_WORDS, true);
        }));
    }

    /**
     * Calculate keyword match score for a code index.
     *
     * @param  array<string>  $searchTerms
     */
    public function calculateScore(CodeIndex $index, array $searchTerms): float
    {
        $score = 0.0;
        $contentLower = mb_strtolower($index->content);
        $pathLower = mb_strtolower($index->file_path);

        foreach ($searchTerms as $term) {
            $termLower = mb_strtolower($term);

            if (str_contains($pathLower, $termLower)) {
                $score += 0.5;
            }

            $count = mb_substr_count($contentLower, $termLower);
            $score += min($count * 0.1, 0.5);
        }

        return min($score / count($searchTerms), 1.0);
    }

    /**
     * Extract relevant content snippet around search terms.
     *
     * @param  array<string>  $searchTerms
     */
    public function extractSnippet(string $content, array $searchTerms): string
    {
        $firstMatch = mb_strlen($content);
        foreach ($searchTerms as $term) {
            $pos = mb_stripos($content, (string) $term);
            if ($pos !== false && $pos < $firstMatch) {
                $firstMatch = $pos;
            }
        }

        $start = max(0, $firstMatch - self::SNIPPET_CONTEXT_BEFORE);
        $excerpt = mb_substr($content, $start, self::SNIPPET_MAX_LENGTH);

        if ($start > 0) {
            $excerpt = '...'.$excerpt;
        }

        if (mb_strlen($content) > $start + self::SNIPPET_MAX_LENGTH) {
            $excerpt .= '...';
        }

        return $excerpt;
    }
}
