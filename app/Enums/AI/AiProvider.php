<?php

declare(strict_types=1);

namespace App\Enums\AI;

/**
 * AI providers supported for BYOK (Bring Your Own Key).
 */
enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAI = 'openai';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
