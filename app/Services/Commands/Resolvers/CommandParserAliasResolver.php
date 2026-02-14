<?php

declare(strict_types=1);

namespace App\Services\Commands\Resolvers;

use App\Enums\Commands\CommandType;

final class CommandParserAliasResolver
{
    /**
     * Resolve a word into a command type alias.
     */
    public function resolve(string $word): ?CommandType
    {
        $directMatches = [
            'explain' => CommandType::Explain,
            'analyze' => CommandType::Analyze,
            'analyse' => CommandType::Analyze,
            'review' => CommandType::Review,
            're-review' => CommandType::Review,
            'rereview' => CommandType::Review,
            'summarize' => CommandType::Summarize,
            'summarise' => CommandType::Summarize,
            'summary' => CommandType::Summarize,
            'find' => CommandType::Find,
            'search' => CommandType::Find,
            'locate' => CommandType::Find,
        ];

        return $directMatches[$word] ?? null;
    }
}
