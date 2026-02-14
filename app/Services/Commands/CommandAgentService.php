<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\CommandRun;
use App\Services\Commands\Builders\CommandPromptBuilder;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\Support\CommandAgentProviderResolver;
use App\Services\Commands\Support\CommandAgentResponseMapper;
use App\Services\Commands\Support\CommandContextHintsNormalizer;
use App\Services\Commands\ValueObjects\CommandExecutionResult;
use App\Services\Commands\ValueObjects\ExecutionMetrics;
use App\Services\Commands\ValueObjects\PullRequestMetadata;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Tool as PrismTool;
use RuntimeException;
use Throwable;

/**
 * AI agent service for executing commands with tool calling capabilities.
 *
 * Implements an agentic loop where the LLM can call tools to gather
 * information and synthesize a response.
 */
final readonly class CommandAgentService implements CommandAgentServiceContract
{
    private const int MAX_ITERATIONS = 20;

    /**
     * Create a new CommandAgentService instance.
     *
     * @param  iterable<int, CommandToolBuilder>  $toolBuilders
     */
    public function __construct(
        private CommandAgentProviderResolver $providerResolver,
        private CommandContextHintsNormalizer $contextHintsNormalizer,
        private PullRequestContextServiceContract $prContextService,
        private CommandPromptBuilder $promptBuilder,
        private CommandPathRulesResolver $pathRulesResolver,
        private CommandAgentResponseMapper $responseMapper,
        private iterable $toolBuilders,
    ) {}

    /**
     * Execute a command using the AI agent.
     */
    public function execute(CommandRun $commandRun): CommandExecutionResult
    {
        $startTime = microtime(true);

        $repository = $commandRun->repository;
        if ($repository === null) {
            throw new RuntimeException('CommandRun has no associated repository');
        }

        // Resolve provider key (BYOK)
        $providerKey = $this->providerResolver->resolveProviderKey($repository);
        $aiProvider = $providerKey->provider;
        $provider = $this->providerResolver->mapToProvider($aiProvider);
        $model = $this->providerResolver->resolveModel($aiProvider, $providerKey);
        $apiKey = $providerKey->encrypted_key;

        if ($apiKey === '') {
            throw NoProviderKeyException::invalidDecryptedKey();
        }

        // Build system prompt
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($commandRun->command_type);

        // Build initial user message, including any PR context
        $prContext = $this->prContextService->buildContext($commandRun);
        $prMetadata = $this->prContextService->getMetadata($commandRun);
        $pathRules = $this->pathRulesResolver->resolve(
            $repository,
            is_array($prMetadata) ? $prMetadata['base_branch'] : null
        );

        // Build tools
        $tools = $this->buildTools($commandRun, $pathRules);

        $userMessage = $this->promptBuilder->buildUserMessage(
            $commandRun->command_type,
            $commandRun->query,
            $prContext,
            $this->contextHintsNormalizer->normalize($commandRun->context_snapshot['context_hints'] ?? null)
        );

        // Determine if extended thinking should be enabled
        $enableThinking = $aiProvider === AiProvider::Anthropic
            && config('prism.providers.anthropic.default_thinking_budget', 2048) > 0;

        $providerOptions = $this->providerResolver->buildProviderOptions($aiProvider, $enableThinking);

        $temperature = $enableThinking ? 1 : 0.3;

        Log::debug('Starting command agent execution', [
            'command_run_id' => $commandRun->id,
            'command_type' => $commandRun->command_type->value,
            'provider' => $provider->value,
            'model' => $model,
            'thinking_enabled' => $enableThinking,
        ]);

        $toolCallVOs = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalThinkingTokens = 0;
        $cacheCreationTokens = 0;
        $cacheReadTokens = 0;

        // Execute with max steps (Prism handles the agentic loop internally)
        try {
            $response = Prism::text()
                ->using($provider, $model, ['api_key' => $apiKey])
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($userMessage)
                ->withTools($tools)
                ->withToolChoice(ToolChoice::Auto)
                ->withMaxSteps(self::MAX_ITERATIONS)
                ->withMaxTokens(4096)
                ->usingTemperature($temperature)
                ->withProviderOptions($providerOptions)
                ->withClientOptions(['timeout' => 300])
                ->asText();

            $toolCallVOs = $this->responseMapper->mapToolCalls($response);
            $usageMetrics = $this->responseMapper->extractUsageMetrics($response);
            $totalInputTokens = $usageMetrics['input_tokens'];
            $totalOutputTokens = $usageMetrics['output_tokens'];
            $totalThinkingTokens = $usageMetrics['thinking_tokens'];
            $cacheCreationTokens = $usageMetrics['cache_creation_input_tokens'];
            $cacheReadTokens = $usageMetrics['cache_read_input_tokens'];

            $answer = $response->text;
            $iterations = count($response->steps);

        } catch (Throwable $throwable) {
            Log::error('Command agent execution failed', [
                'command_run_id' => $commandRun->id,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        Log::info('Command agent execution completed', [
            'command_run_id' => $commandRun->id,
            'iterations' => $iterations,
            'tool_calls' => count($toolCallVOs),
            'duration_ms' => $durationMs,
        ]);

        return new CommandExecutionResult(
            answer: $answer,
            toolCalls: $toolCallVOs,
            iterations: $iterations,
            metrics: new ExecutionMetrics(
                inputTokens: $totalInputTokens,
                outputTokens: $totalOutputTokens,
                thinkingTokens: $totalThinkingTokens,
                cacheCreationInputTokens: $cacheCreationTokens,
                cacheReadInputTokens: $cacheReadTokens,
                durationMs: $durationMs,
                model: $model,
                provider: $provider->value,
            ),
            prMetadata: PullRequestMetadata::fromArray($prMetadata),
        );
    }

    /**
     * Build the tools available to the agent.
     *
     * @return array<int, PrismTool>
     */
    private function buildTools(CommandRun $commandRun, CommandPathRules $pathRules): array
    {
        $tools = [];

        foreach ($this->toolBuilders as $toolBuilder) {
            $tools[] = $toolBuilder->build($commandRun, $pathRules);
        }

        return $tools;
    }
}
