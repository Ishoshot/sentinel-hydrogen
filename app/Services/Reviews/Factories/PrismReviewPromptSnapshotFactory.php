<?php

declare(strict_types=1);

namespace App\Services\Reviews\Factories;

use App\Services\Reviews\ReviewPromptBuilder;
use App\Services\Reviews\ValueObjects\PromptSnapshot;

/**
 * Builds stable prompt snapshots for persisted review telemetry.
 */
final class PrismReviewPromptSnapshotFactory
{
    /**
     * Build a prompt snapshot value object.
     */
    public function make(string $systemPrompt, string $userPrompt): PromptSnapshot
    {
        return PromptSnapshot::fromArray([
            'system' => [
                'version' => ReviewPromptBuilder::SYSTEM_PROMPT_VERSION,
                'hash' => hash('sha256', $systemPrompt),
            ],
            'user' => [
                'version' => ReviewPromptBuilder::USER_PROMPT_VERSION,
                'hash' => hash('sha256', $userPrompt),
            ],
            'hash_algorithm' => 'sha256',
        ]);
    }
}
