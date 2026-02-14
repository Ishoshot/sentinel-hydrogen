<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

final readonly class LinkedIssueReferenceExtractor
{
    /**
     * @var array<string>
     */
    private const array ISSUE_PATTERNS = [
        '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s*#(\d+)/i',
        '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+(\d+)/i',
        '/#(\d+)/',
    ];

    /**
     * @return array<int>
     */
    public function extractIssueNumbers(string $body): array
    {
        $numbers = [];

        foreach (self::ISSUE_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $match) {
                    $number = (int) $match;
                    if ($number > 0 && ! in_array($number, $numbers, true)) {
                        $numbers[] = $number;
                    }
                }
            }
        }

        return $numbers;
    }
}
