<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\AiOption;
use App\Models\CommandRun;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Commands\Builders\CommandPromptBuilder;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\Tools\FindSymbolTool;
use App\Services\Commands\Tools\GetFileStructureTool;
use App\Services\Commands\Tools\ListFilesTool;
use App\Services\Commands\Tools\ReadFileTool;
use App\Services\Commands\Tools\SearchCodeTool;
use App\Services\Commands\Tools\SearchPatternTool;
use App\Services\Commands\ValueObjects\CommandExecutionResult;
use App\Services\Commands\ValueObjects\ExecutionMetrics;
use App\Services\Commands\ValueObjects\PullRequestMetadata;
use App\Services\Commands\ValueObjects\ToolCall;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
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

    private const int THINKING_BUDGET = 8192;

    /**
     * Create a new CommandAgentService instance.
     */
    public function __construct(
        private ProviderKeyResolver $keyResolver,
        private PullRequestContextServiceContract $prContextService,
        private CommandPromptBuilder $promptBuilder,
        private CommandPathRulesResolver $pathRulesResolver,
        private SearchCodeTool $searchCodeTool,
        private SearchPatternTool $searchPatternTool,
        private FindSymbolTool $findSymbolTool,
        private ListFilesTool $listFilesTool,
        private ReadFileTool $readFileTool,
        private GetFileStructureTool $getFileStructureTool,
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
        $providerKey = $this->resolveProviderKey($repository);
        $aiProvider = $providerKey->provider;
        $provider = $this->mapToProvider($aiProvider);
        $model = $this->resolveModel($aiProvider, $providerKey);
        $apiKey = $providerKey->encrypted_key;

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
            $this->normalizeContextHints($commandRun->context_snapshot['context_hints'] ?? null)
        );

        // Determine if extended thinking should be enabled
        $enableThinking = $aiProvider === AiProvider::Anthropic
            && config('prism.providers.anthropic.default_thinking_budget', 2048) > 0;

        $providerOptions = $this->buildProviderOptions($aiProvider, $enableThinking);

        $temperature = $enableThinking ? 1 : 0.3;

        Log::debug('Starting command agent execution', [
            'command_run_id' => $commandRun->id,
            'command_type' => $commandRun->command_type->value,
            'provider' => $provider->value,
            'model' => $model,
            'thinking_enabled' => $enableThinking,
        ]);

        /** @var array<int, array{name: string, arguments: array<string, mixed>, result: string}> $allToolCalls */
        $allToolCalls = [];
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

            // Collect tool calls from all steps
            foreach ($response->steps as $step) {
                foreach ($step->toolCalls as $toolCall) {
                    $allToolCalls[] = [
                        'name' => $toolCall->name,
                        'arguments' => $toolCall->arguments(),
                        'result' => '', // Will be populated below
                    ];
                }
            }

            // Match tool calls with results
            foreach ($response->toolResults as $index => $toolResult) {
                if (isset($allToolCalls[$index])) {
                    $result = $toolResult->result;
                    $allToolCalls[$index]['result'] = is_array($result)
                        ? json_encode($result, JSON_THROW_ON_ERROR)
                        : (string) ($result ?? '');
                }
            }

            $totalInputTokens = $response->usage->promptTokens;
            $totalOutputTokens = $response->usage->completionTokens;

            // Extract thinking tokens if available (check Meta object)
            if (property_exists($response->meta, 'thinkingTokens') && $response->meta->thinkingTokens !== null) {
                $totalThinkingTokens = (int) $response->meta->thinkingTokens;
            }

            // Extract cache metrics if available (Anthropic prompt caching)
            if (property_exists($response->usage, 'cacheCreationInputTokens') && $response->usage->cacheCreationInputTokens !== null) {
                $cacheCreationTokens = (int) $response->usage->cacheCreationInputTokens;
            }

            if (isset($response->usage->cacheReadInputTokens)) {
                $cacheReadTokens = (int) $response->usage->cacheReadInputTokens;
            }

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
            'tool_calls' => count($allToolCalls),
            'duration_ms' => $durationMs,
        ]);

        $toolCallVOs = array_map(
            fn (array $tc): ToolCall => new ToolCall(
                name: $tc['name'],
                arguments: $tc['arguments'],
                result: $tc['result'],
            ),
            array_values($allToolCalls)
        );

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
        return [
            $this->searchCodeTool->build($commandRun, $pathRules),
            $this->searchPatternTool->build($commandRun, $pathRules),
            $this->findSymbolTool->build($commandRun, $pathRules),
            $this->listFilesTool->build($commandRun, $pathRules),
            $this->readFileTool->build($commandRun, $pathRules),
            $this->getFileStructureTool->build($commandRun, $pathRules),
        ];
    }

    /**
     * Resolve the provider key for the repository.
     */
    private function resolveProviderKey(Repository $repository): ProviderKey
    {
        $providers = [AiProvider::Anthropic, AiProvider::OpenAI];

        foreach ($providers as $aiProvider) {
            $providerKey = $this->keyResolver->getProviderKey($repository, $aiProvider);

            if ($providerKey instanceof ProviderKey) {
                return $providerKey;
            }
        }

        throw NoProviderKeyException::noProvidersConfigured();
    }

    /**
     * Map AiProvider enum to Prism Provider enum.
     */
    private function mapToProvider(AiProvider $aiProvider): Provider
    {
        return match ($aiProvider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::OpenAI => Provider::OpenAI,
        };
    }

    /**
     * Build provider-specific options for the Prism request.
     *
     * @return array<string, mixed>
     */
    private function buildProviderOptions(AiProvider $aiProvider, bool $enableThinking): array
    {
        if ($aiProvider !== AiProvider::Anthropic) {
            return [];
        }

        $options = ['cacheType' => 'ephemeral'];

        if ($enableThinking) {
            $options['thinking'] = ['enabled' => true, 'budget_tokens' => self::THINKING_BUDGET];
        }

        return $options;
    }

    /**
     * Normalize context hints into a typed shape.
     *
     * @return array{files?: array<string>, symbols?: array<string>, lines?: array<array{start: int, end: int|null}>}|null
     */
    private function normalizeContextHints(mixed $contextHints): ?array
    {
        if (! is_array($contextHints)) {
            return null;
        }

        $normalized = [];

        if (isset($contextHints['files']) && is_array($contextHints['files'])) {
            $files = array_values(array_filter(
                $contextHints['files'],
                is_string(...)
            ));

            if ($files !== []) {
                $normalized['files'] = $files;
            }
        }

        if (isset($contextHints['symbols']) && is_array($contextHints['symbols'])) {
            $symbols = array_values(array_filter(
                $contextHints['symbols'],
                is_string(...)
            ));

            if ($symbols !== []) {
                $normalized['symbols'] = $symbols;
            }
        }

        if (isset($contextHints['lines']) && is_array($contextHints['lines'])) {
            $lines = [];

            foreach ($contextHints['lines'] as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $start = $line['start'] ?? null;
                $end = $line['end'] ?? null;

                if (! is_int($start)) {
                    continue;
                }

                $lines[] = [
                    'start' => $start,
                    'end' => is_int($end) ? $end : null,
                ];
            }

            if ($lines !== []) {
                $normalized['lines'] = $lines;
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * Resolve the AI model to use.
     */
    private function resolveModel(AiProvider $aiProvider, ProviderKey $providerKey): string
    {
        // 1. Use model from provider key if user selected one
        if ($providerKey->providerModel !== null) {
            return $providerKey->providerModel->identifier;
        }

        // 2. Get default from database
        $defaultModel = AiOption::getDefault($aiProvider);
        if ($defaultModel instanceof AiOption) {
            return $defaultModel->identifier;
        }

        // 3. Hardcoded fallback (safety net)
        return match ($aiProvider) {
            AiProvider::Anthropic => 'claude-sonnet-4-5-20250929',
            AiProvider::OpenAI => 'gpt-4o',
        };
    }
}
