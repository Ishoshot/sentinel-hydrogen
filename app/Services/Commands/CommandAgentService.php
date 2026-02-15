<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Models\CommandRun;
use App\Services\Commands\Builders\CommandAgentExecutionContextBuilder;
use App\Services\Commands\Clients\CommandAgentPrismClient;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Loggers\CommandAgentTelemetryLogger;
use App\Services\Commands\Mappers\CommandAgentResponseMapper;
use App\Services\Commands\ValueObjects\CommandExecutionResult;
use App\Services\Commands\ValueObjects\ExecutionMetrics;
use App\Services\Commands\ValueObjects\PullRequestMetadata;
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
        private CommandAgentExecutionContextBuilder $executionContextBuilder,
        private CommandAgentPrismClient $prismClient,
        private CommandAgentResponseMapper $responseMapper,
        private CommandAgentTelemetryLogger $telemetryLogger,
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

        $executionContext = $this->executionContextBuilder->prepare($commandRun, $this->toolBuilders);
        $this->telemetryLogger->logStarted($commandRun, $executionContext);

        $toolCallVOs = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalThinkingTokens = 0;
        $cacheCreationTokens = 0;
        $cacheReadTokens = 0;

        try {
            $response = $this->prismClient->execute(
                provider: $executionContext->provider,
                model: $executionContext->model,
                apiKey: $executionContext->apiKey,
                systemPrompt: $executionContext->systemPrompt,
                userMessage: $executionContext->userMessage,
                tools: $executionContext->tools,
                temperature: $executionContext->temperature,
                providerOptions: $executionContext->providerOptions,
                maxIterations: self::MAX_ITERATIONS,
            );

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
            $this->telemetryLogger->logFailed($commandRun, $throwable);

            throw $throwable;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $this->telemetryLogger->logCompleted($commandRun, $iterations, count($toolCallVOs), $durationMs);

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
                model: $executionContext->model,
                provider: $executionContext->provider->value,
            ),
            prMetadata: PullRequestMetadata::fromArray($executionContext->prMetadata),
        );
    }
}
