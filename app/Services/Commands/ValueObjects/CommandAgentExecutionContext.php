<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

use App\Enums\AI\AiProvider;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Tool as PrismTool;

final readonly class CommandAgentExecutionContext
{
    /**
     * @param  array<int, PrismTool>  $tools
     * @param  array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool, base_branch?: string, head_branch?: string}|null  $prMetadata
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public AiProvider $aiProvider,
        public Provider $provider,
        public string $model,
        public string $apiKey,
        public bool $thinkingEnabled,
        public string $systemPrompt,
        public string $userMessage,
        public array $tools,
        public ?array $prMetadata,
        public array $providerOptions,
        public float $temperature,
    ) {}
}
