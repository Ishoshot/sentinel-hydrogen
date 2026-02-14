<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Parses unified diff patches into the set of modified target lines.
 */
final readonly class ImpactPatchModifiedLineParser
{
    /**
     * @return array<int, int>
     */
    public function parse(string $patch): array
    {
        $modifiedLines = [];
        $currentLine = 0;

        foreach (explode("\n", $patch) as $line) {
            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches) === 1) {
                $currentLine = (int) $matches[1];

                continue;
            }

            if ($line === '') {
                continue;
            }

            $firstChar = $line[0] ?? '';

            if ($firstChar === '+' && ! str_starts_with($line, '+++')) {
                $modifiedLines[] = $currentLine;
                $currentLine++;
            } elseif ($firstChar === '-' && ! str_starts_with($line, '---')) {
                continue;
            } elseif ($firstChar === ' ') {
                $currentLine++;
            }
        }

        return $modifiedLines;
    }
}
