<?php

declare(strict_types=1);

namespace App\Services\Commands\Builders;

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\CommandPathRulesResolver;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Contracts\IssueContextServiceContract;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\Normalizers\CommandContextHintsNormalizer;
use App\Services\Commands\Resolvers\CommandAgentProviderResolver;
use App\Services\Commands\ValueObjects\CommandAgentExecutionContext;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Tool as PrismTool;
use RuntimeException;
use Throwable;

final readonly class CommandAgentExecutionContextBuilder
{
    /**
     * Create a new CommandAgentExecutionContextBuilder instance.
     */
    public function __construct(
        private CommandAgentProviderResolver $providerResolver,
        private CommandContextHintsNormalizer $contextHintsNormalizer,
        private PullRequestContextServiceContract $prContextService,
        private IssueContextServiceContract $issueContextService,
        private IssueRetrievalContextBuilder $issueRetrievalContextBuilder,
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
        $issueContext = $this->resolveIssueContext($commandRun);
        $prMetadata = $this->prContextService->getMetadata($commandRun);
        $pathRules = $this->pathRulesResolver->resolve(
            $repository,
            is_array($prMetadata) ? $prMetadata['base_branch'] : null
        );

        $tools = $this->buildTools($commandRun, $pathRules, $toolBuilders);
        $issueRetrievalContext = $this->resolveIssueRetrievalContext($commandRun, $pathRules, $issueContext);

        $metadata = is_array($commandRun->metadata) ? $commandRun->metadata : [];
        $inputClassification = $this->normalizeInputClassification($metadata['input_classification'] ?? null);
        $normalizedContextHints = $this->contextHintsNormalizer->normalize($commandRun->context_snapshot['context_hints'] ?? null);
        $contextHintFilesCount = is_array($normalizedContextHints['files'] ?? null)
            ? count($normalizedContextHints['files'])
            : 0;
        $contextHintSymbolsCount = is_array($normalizedContextHints['symbols'] ?? null)
            ? count($normalizedContextHints['symbols'])
            : 0;
        $contextHintLinesCount = is_array($normalizedContextHints['lines'] ?? null)
            ? count($normalizedContextHints['lines'])
            : 0;

        $userMessage = $this->promptBuilder->buildUserMessage(
            commandType: $commandRun->command_type,
            query: $commandRun->query,
            untrustedContext: $prContext,
            contextHints: $normalizedContextHints,
            inputClassification: $inputClassification,
            untrustedIssueContext: $issueContext,
            untrustedIssueRetrievalContext: $issueRetrievalContext,
        );

        Log::debug('Prepared command execution context', [
            'command_run_id' => $commandRun->id,
            'repository' => $repository->full_name,
            'has_pr_context' => $prContext !== null,
            'pr_context_chars' => $prContext !== null ? mb_strlen($prContext) : 0,
            'has_issue_context' => $issueContext !== null,
            'issue_context_chars' => $issueContext !== null ? mb_strlen($issueContext) : 0,
            'has_issue_retrieval_context' => $issueRetrievalContext !== null,
            'issue_retrieval_context_chars' => $issueRetrievalContext !== null ? mb_strlen($issueRetrievalContext) : 0,
            'context_hint_files' => $contextHintFilesCount,
            'context_hint_symbols' => $contextHintSymbolsCount,
            'context_hint_lines' => $contextHintLinesCount,
            'tools_count' => count($tools),
            'has_input_classification' => $inputClassification !== null,
        ]);

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
     * @return array{
     *     decision?: string,
     *     risk_level?: string,
     *     risk_types?: array<int, string>,
     *     confidence?: float|int,
     *     signals?: array<int, array{source?: string, code?: string, severity?: string}>
     * }|null
     */
    private function normalizeInputClassification(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];

        if (is_string($value['decision'] ?? null)) {
            $normalized['decision'] = $value['decision'];
        }

        if (is_string($value['risk_level'] ?? null)) {
            $normalized['risk_level'] = $value['risk_level'];
        }

        if (is_numeric($value['confidence'] ?? null)) {
            $normalized['confidence'] = (float) $value['confidence'];
        }

        if (is_array($value['risk_types'] ?? null)) {
            $normalized['risk_types'] = array_values(array_filter($value['risk_types'], is_string(...)));
        }

        if (is_array($value['signals'] ?? null)) {
            $signals = [];

            foreach ($value['signals'] as $signal) {
                if (! is_array($signal)) {
                    continue;
                }

                $normalizedSignal = [];

                if (is_string($signal['source'] ?? null)) {
                    $normalizedSignal['source'] = $signal['source'];
                }

                if (is_string($signal['code'] ?? null)) {
                    $normalizedSignal['code'] = $signal['code'];
                }

                if (is_string($signal['severity'] ?? null)) {
                    $normalizedSignal['severity'] = $signal['severity'];
                }

                if ($normalizedSignal !== []) {
                    $signals[] = $normalizedSignal;
                }
            }

            if ($signals !== []) {
                $normalized['signals'] = $signals;
            }
        }

        return $normalized === [] ? null : $normalized;
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

    /**
     * Resolve issue context with a fail-open fallback.
     */
    private function resolveIssueContext(CommandRun $commandRun): ?string
    {
        try {
            return $this->issueContextService->buildContext($commandRun);
        } catch (Throwable $throwable) {
            Log::warning('Failed to resolve issue context for command run', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
                'issue_number' => $commandRun->issue_number,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve retrieval context with a fail-open fallback.
     */
    private function resolveIssueRetrievalContext(
        CommandRun $commandRun,
        CommandPathRules $pathRules,
        ?string $issueContext,
    ): ?string {
        try {
            return $this->issueRetrievalContextBuilder->build($commandRun, $pathRules, $issueContext);
        } catch (Throwable $throwable) {
            Log::warning('Failed to resolve issue retrieval context for command run', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
                'issue_number' => $commandRun->issue_number,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
