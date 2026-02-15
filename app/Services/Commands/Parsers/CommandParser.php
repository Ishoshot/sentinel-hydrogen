<?php

declare(strict_types=1);

namespace App\Services\Commands\Parsers;

use App\Enums\Commands\CommandType;
use App\Services\Commands\Resolvers\CommandParserAliasResolver;
use App\Services\Commands\ValueObjects\ContextHints;
use App\Services\Commands\ValueObjects\ParsedCommand;

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
     * Create a new parser instance.
     */
    public function __construct(
        private CommandParserAliasResolver $aliasResolver = new CommandParserAliasResolver(),
        private CommandQueryContextHintsParser $contextHintsExtractor = new CommandQueryContextHintsParser(),
    ) {}

    /**
     * Parse a comment body for @sentinel commands.
     */
    public function parse(string $commentBody): ?ParsedCommand
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
            return ParsedCommand::found(
                commandType: CommandType::Explain,
                query: '',
                contextHints: $this->extractContextHints(''),
            );
        }

        // Parse command type and query
        $parsed = $this->parseCommandAndQuery($afterMention);

        return ParsedCommand::found(
            commandType: $parsed['command_type'],
            query: $parsed['query'],
            contextHints: $this->extractContextHints($parsed['query']),
        );
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
        return $this->aliasResolver->resolve($word);
    }

    /**
     * Extract context hints from the query.
     */
    private function extractContextHints(string $query): ContextHints
    {
        return $this->contextHintsExtractor->extract($query);
    }
}
