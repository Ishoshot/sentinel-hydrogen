<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use App\Services\Reviews\ValueObjects\ReviewFinding;

/**
 * Sorts review findings by severity, confidence, file path, and line number.
 */
final readonly class FindingSorter
{
    /**
     * Sort findings by severity (desc), confidence (desc), file path (asc), line (asc), title (asc).
     *
     * @param  array<int, ReviewFinding>  $findings
     * @return array<int, ReviewFinding>
     */
    public function sort(array $findings): array
    {
        usort($findings, fn (ReviewFinding $a, ReviewFinding $b): int => ($b->severity->priority() <=> $a->severity->priority())
            ?: ($b->confidence <=> $a->confidence)
            ?: $this->compareNullableStrings($a->filePath, $b->filePath)
            ?: $this->compareNullableInts($a->lineStart, $b->lineStart)
            ?: strcmp($a->title, $b->title)
        );

        return $findings;
    }

    /**
     * Compare nullable strings while sorting null values last.
     */
    private function compareNullableStrings(?string $left, ?string $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return strcmp($left, $right);
    }

    /**
     * Compare nullable integers while sorting null values last.
     */
    private function compareNullableInts(?int $left, ?int $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }
}
