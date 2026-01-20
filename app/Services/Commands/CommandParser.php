<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Enums\CommandType;

/**
 * Parses @sentinel commands from GitHub issue/PR comments.
 *
 * Extracts command type, query, and context hints from mention text.
 */
final readonly class CommandParser
{
    /**
     * The bot mention trigger.
     */
    private const string MENTION_TRIGGER = '@sentinel';

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
     * Parse a comment body for @sentinel commands.
     *
     * @return array{found: bool, command_type: CommandType|null, query: string|null, context_hints: array{files: array<string>, symbols: array<string>, lines: array<array{start: int, end: int|null}>}}|null
     */
    public function parse(string $commentBody): ?array
    {
        // Check if the comment mentions @sentinel
        $mentionPos = mb_stripos($commentBody, self::MENTION_TRIGGER);

        if ($mentionPos === false) {
            return null;
        }

        // Extract the text after @sentinel
        $afterMention = mb_substr($commentBody, $mentionPos + mb_strlen(self::MENTION_TRIGGER));
        $afterMention = mb_trim($afterMention);

        if ($afterMention === '') {
            return [
                'found' => true,
                'command_type' => CommandType::Explain,
                'query' => '',
                'context_hints' => $this->extractContextHints(''),
            ];
        }

        // Parse command type and query
        $parsed = $this->parseCommandAndQuery($afterMention);

        return [
            'found' => true,
            'command_type' => $parsed['command_type'],
            'query' => $parsed['query'],
            'context_hints' => $this->extractContextHints($parsed['query']),
        ];
    }

    /**
     * Check if a comment body contains an @sentinel mention.
     */
    public function hasMention(string $commentBody): bool
    {
        return mb_stripos($commentBody, self::MENTION_TRIGGER) !== false;
    }

    /**
     * Parse the command type and query from text after the mention.
     *
     * @return array{command_type: CommandType, query: string}
     */
    private function parseCommandAndQuery(string $text): array
    {
        // Split into words
        $words = preg_split('/\s+/', $text, 2);

        if ($words === false || $words === []) {
            return [
                'command_type' => CommandType::Explain,
                'query' => '',
            ];
        }

        $firstWord = mb_strtolower($words[0]);
        $rest = $words[1] ?? '';

        // Check if first word is a known command
        $commandType = $this->matchCommandType($firstWord);

        if ($commandType instanceof CommandType) {
            return [
                'command_type' => $commandType,
                'query' => mb_trim($rest),
            ];
        }

        // No command specified, default to explain with full text as query
        return [
            'command_type' => CommandType::Explain,
            'query' => mb_trim($text),
        ];
    }

    /**
     * Match a word to a command type.
     */
    private function matchCommandType(string $word): ?CommandType
    {
        // Direct matches
        $directMatches = [
            'explain' => CommandType::Explain,
            'analyze' => CommandType::Analyze,
            'analyse' => CommandType::Analyze, // British spelling
            'review' => CommandType::Review,
            're-review' => CommandType::Review, // Re-trigger review
            'rereview' => CommandType::Review, // Re-trigger review (no hyphen)
            'summarize' => CommandType::Summarize,
            'summarise' => CommandType::Summarize, // British spelling
            'summary' => CommandType::Summarize,
            'find' => CommandType::Find,
            'search' => CommandType::Find,
            'locate' => CommandType::Find,
        ];

        return $directMatches[$word] ?? null;
    }

    /**
     * Extract context hints from the query.
     *
     * @return array{files: array<string>, symbols: array<string>, lines: array<array{start: int, end: int|null}>}
     */
    private function extractContextHints(string $query): array
    {
        return [
            'files' => $this->extractFilePaths($query),
            'symbols' => $this->extractSymbols($query),
            'lines' => $this->extractLineNumbers($query),
        ];
    }

    /**
     * Extract file paths from the query.
     *
     * @return array<string>
     */
    private function extractFilePaths(string $query): array
    {
        $matches = [];
        if (preg_match_all(self::FILE_PATH_PATTERN, $query, $matches)) {
            // Filter out common false positives
            $filtered = array_filter($matches[1],
                // Must have at least one directory separator or be a known file pattern
                fn (string $path): bool => str_contains($path, '/')
                || preg_match('/\.(php|js|ts|tsx|jsx|vue|py|rb|go|rs|java|kt|cs|swift|sql|yaml|yml|json|md)$/i', $path));

            return array_values(array_unique($filtered));
        }

        return [];
    }

    /**
     * Extract symbol names from the query.
     *
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
     * Extract line number references from the query.
     *
     * @return array<array{start: int, end: int|null}>
     */
    private function extractLineNumbers(string $query): array
    {
        $lines = [];
        $matches = [];

        if (preg_match_all(self::LINE_NUMBER_PATTERN, $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // "line 42" format
                // @phpstan-ignore notIdentical.alwaysTrue (empty string possible when alternation doesn't match)
                if (isset($match[1]) && $match[1] !== '' && $match[1] !== '0') {
                    $lines[] = [
                        'start' => (int) $match[1],
                        'end' => null,
                    ];
                }
                // "L42" or "L42-L50" format
                // @phpstan-ignore notIdentical.alwaysTrue (empty string possible when alternation doesn't match)
                elseif (isset($match[2]) && $match[2] !== '' && $match[2] !== '0') {
                    $lines[] = [
                        'start' => (int) $match[2],
                        'end' => empty($match[3]) ? null : (int) $match[3],
                    ];
                }
            }
        }

        return $lines;
    }
}
