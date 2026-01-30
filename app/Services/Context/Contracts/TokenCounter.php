<?php

declare(strict_types=1);

namespace App\Services\Context\Contracts;

use App\Services\Context\TokenCounting\TokenCounterContext;

interface TokenCounter
{
    /**
     * Count tokens for a raw text segment.
     */
    public function countTextTokens(string $text, TokenCounterContext $context): int;

    /**
     * Count tokens for a system + user prompt pair.
     */
    public function countMessageTokens(?string $systemPrompt, string $userPrompt, TokenCounterContext $context): int;
}
