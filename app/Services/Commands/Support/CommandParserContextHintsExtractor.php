<?php

declare(strict_types=1);

namespace App\Services\Commands\Support;

use App\Services\Commands\ValueObjects\ContextHints;
use App\Services\Commands\ValueObjects\LineRange;

final class CommandParserContextHintsExtractor
{
    /**
     * Pattern to match file paths in the query.
     */
    private const string FILE_PATH_PATTERN = '/(?:^|[\s`\'"])([a-zA-Z0-9_\-\/\.]+\.[a-zA-Z0-9]+)(?:[\s`\'"]|$)/';

    /**
     * Pattern to match symbols (ClassName, ClassName::method, method()).
     */
    private const string SYMBOL_PATTERN = '/(?:^|[\s`])([A-Z]\w*(?:::\w+)?|\b[a-z]\w*\(\))(?:[\s`]|$)/';

    /**
     * Pattern to match line number references.
     */
    private const string LINE_NUMBER_PATTERN = '/(?:line\s*#?\s*(\d+)|L(\d+)(?:-L?(\d+))?)/i';

    /**
     * Extract context hints from the query.
     */
    public function extract(string $query): ContextHints
    {
        return new ContextHints(
            files: $this->extractFilePaths($query),
            symbols: $this->extractSymbols($query),
            lines: $this->extractLineRanges($query),
        );
    }

    /**
     * @return array<string>
     */
    private function extractFilePaths(string $query): array
    {
        $matches = [];
        if (preg_match_all(self::FILE_PATH_PATTERN, $query, $matches)) {
            $filtered = array_filter($matches[1],
                fn (string $path): bool => str_contains($path, '/')
                || preg_match('/\.(php|js|ts|tsx|jsx|vue|py|rb|go|rs|java|kt|cs|swift|sql|yaml|yml|json|md)$/i', $path));

            return array_values(array_unique($filtered));
        }

        return [];
    }

    /**
     * @return array<string>
     */
    private function extractSymbols(string $query): array
    {
        $matches = [];
        if (preg_match_all(self::SYMBOL_PATTERN, $query, $matches)) {
            return array_values(array_unique($matches[1]));
        }

        return [];
    }

    /**
     * @return array<LineRange>
     */
    private function extractLineRanges(string $query): array
    {
        $lines = [];
        $matches = [];

        if (preg_match_all(self::LINE_NUMBER_PATTERN, $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // @phpstan-ignore notIdentical.alwaysTrue (empty string possible when alternation doesn't match)
                if (isset($match[1]) && $match[1] !== '' && $match[1] !== '0') {
                    $lines[] = new LineRange(
                        start: (int) $match[1],
                    );
                }
                // @phpstan-ignore notIdentical.alwaysTrue (empty string possible when alternation doesn't match)
                elseif (isset($match[2]) && $match[2] !== '' && $match[2] !== '0') {
                    $lines[] = new LineRange(
                        start: (int) $match[2],
                        end: empty($match[3]) ? null : (int) $match[3],
                    );
                }
            }
        }

        return $lines;
    }
}
