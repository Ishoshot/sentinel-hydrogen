<?php

declare(strict_types=1);

namespace App\Services\Commands\Support;

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\CommandRun;
use App\Services\Commands\Builders\CommandPromptBuilder;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\CommandPathRulesResolver;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use Prism\Prism\Tool as PrismTool;
use RuntimeException;

final readonly class CommandAgentExecutionContextPreparer
{
    public function __construct(
        private CommandAgentProviderResolver $providerResolver,
        private CommandContextHintsNormalizer $contextHintsNormalizer,
        private PullRequestContextServiceContract $prContextService,
        private CommandPromptBuilder $promptBuilder,
        private CommandPathRulesResolver $pathRulesResolver,
    ) {}

    /**
     * Prepare provider/model/options, prompt content, tools, and metadata.
     *
     * @param  iterable<int, CommandToolBuilder>  $toolBuilders
     */
    public function prepare(CommandRun $commandRun, iterable $toolBuilders): CommandAgentExecutionContext
    {
        $repository = $commandRun->repository;
        if ($repository === null) {
            throw new RuntimeException('CommandRun has no associated repository');
        }

        $providerKey = $this->providerResolver->resolveProviderKey($repository);
        $aiProvider = $providerKey->provider;
        $provider = $this->providerResolver->mapToProvider($aiProvider);
        $model = $this->providerResolver->resolveModel($aiProvider, $providerKey);
        $apiKey = $providerKey->encrypted_key;

        if ($apiKey === '') {
            throw NoProviderKeyException::invalidDecryptedKey();
        }

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($commandRun->command_type);

        $prContext = $this->prContextService->buildContext($commandRun);
        $prMetadata = $this->prContextService->getMetadata($commandRun);
        $pathRules = $this->pathRulesResolver->resolve(
            $repository,
            is_array($prMetadata) ? $prMetadata['base_branch'] : null
        );

        $tools = $this->buildTools($commandRun, $pathRules, $toolBuilders);

        $userMessage = $this->promptBuilder->buildUserMessage(
            $commandRun->command_type,
            $commandRun->query,
            $prContext,
            $this->contextHintsNormalizer->normalize($commandRun->context_snapshot['context_hints'] ?? null)
        );

        $enableThinking = $aiProvider === AiProvider::Anthropic
            && config('prism.providers.anthropic.default_thinking_budget', 2048) > 0;

        $providerOptions = $this->providerResolver->buildProviderOptions($aiProvider, $enableThinking);
        $temperature = $enableThinking ? 1 : 0.3;
        /** @var array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool, base_branch?: string, head_branch?: string}|null $resolvedPrMetadata */
        $resolvedPrMetadata = is_array($prMetadata) ? $prMetadata : null;

        return new CommandAgentExecutionContext(
            aiProvider: $aiProvider,
            provider: $provider,
            model: $model,
            apiKey: $apiKey,
            thinkingEnabled: $enableThinking,
            systemPrompt: $systemPrompt,
            userMessage: $userMessage,
            tools: $tools,
            prMetadata: $resolvedPrMetadata,
            providerOptions: $providerOptions,
            temperature: $temperature,
        );
    }

    /**
     * @param  iterable<int, CommandToolBuilder>  $toolBuilders
     * @return array<int, PrismTool>
     */
    private function buildTools(CommandRun $commandRun, CommandPathRules $pathRules, iterable $toolBuilders): array
    {
        /** @var array<int, PrismTool> $tools */
        $tools = [];

        foreach ($toolBuilders as $toolBuilder) {
            $tools[] = $toolBuilder->build($commandRun, $pathRules);
        }

        return $tools;
    }
}
