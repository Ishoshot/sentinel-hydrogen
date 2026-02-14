<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Filters\Support\RelevanceScorer;

/**
 * Prioritizes files by relevance and importance.
 *
 * Sorts files to ensure the most important changes are reviewed first,
 * which helps when token limits require truncation.
 */
final readonly class RelevanceFilter implements ContextFilter
{
    /**
     * Maximum number of files to keep after filtering.
     */
    private const int MAX_FILES = 50;

    /**
     * Create a new relevance filter instance.
     */
    public function __construct(
        private RelevanceScorer $scorer = new RelevanceScorer,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'relevance';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 40; // Run after sensitive data filter, before token limit
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        if ($bag->files === []) {
            return;
        }

        $scoredFiles = array_map(
            fn (array $file): array => [...$file, '_score' => $this->scorer->score($file)],
            $bag->files
        );

        usort($scoredFiles, fn (array $a, array $b): int => $b['_score'] <=> $a['_score']);

        $bag->files = array_map(function (array $file): array {
            unset($file['_score']);

            return $file;
        }, array_slice($scoredFiles, 0, self::MAX_FILES));

        $bag->recalculateMetrics();
    }
}
