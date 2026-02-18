<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Models\CommandRun;
use App\Services\Commands\Builders\CommandInputClassificationPromptBuilder;
use App\Services\Commands\Builders\CommandInputClassificationSchemaBuilder;
use App\Services\Commands\Clients\PrismStructuredCommandInputClassifierClient;
use App\Services\Commands\Contracts\CommandInputClassificationServiceContract;
use App\Services\Commands\Mappers\CommandInputClassificationResultMapper;
use App\Services\Commands\Policies\CommandInputDecisionPolicy;
use App\Services\Commands\Policies\CommandInputRulesPolicy;
use App\Services\Commands\Resolvers\CommandAgentProviderResolver;
use App\Services\Commands\ValueObjects\CommandInputClassificationResult;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CommandInputClassificationService implements CommandInputClassificationServiceContract
{
    /**
     * Create a new classification service instance.
     */
    public function __construct(
        private CommandInputRulesPolicy $rulesPolicy,
        private CommandInputDecisionPolicy $decisionPolicy,
        private CommandAgentProviderResolver $providerResolver,
        private CommandInputClassificationPromptBuilder $promptBuilder,
        private CommandInputClassificationSchemaBuilder $schemaBuilder,
        private PrismStructuredCommandInputClassifierClient $structuredClassifierClient,
        private CommandInputClassificationResultMapper $resultMapper,
    ) {}

    /**
     * Classify a command run query with deterministic and model-based checks.
     */
    public function classify(CommandRun $commandRun): CommandInputClassificationResult
    {
        $rulesAssessment = $this->rulesPolicy->classify($commandRun->query);

        if ($rulesAssessment->blocksExecution()) {
            return $rulesAssessment;
        }

        $llmAssessment = $this->classifyWithModel($commandRun, $rulesAssessment);

        return $this->decisionPolicy->resolve($rulesAssessment, $llmAssessment);
    }

    /**
     * Execute structured model classification.
     */
    private function classifyWithModel(
        CommandRun $commandRun,
        CommandInputClassificationResult $rulesAssessment,
    ): ?CommandInputClassificationResult {
        $repository = $commandRun->repository;

        if ($repository === null) {
            return null;
        }

        try {
            $providerKey = $this->providerResolver->resolveProviderKey($repository);
            $provider = $this->providerResolver->mapToProvider($providerKey->provider);
            $model = $this->providerResolver->resolveModel($providerKey->provider, $providerKey);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt();
            $userPrompt = $this->promptBuilder->buildUserPrompt(
                $commandRun->command_type,
                $commandRun->query,
                $rulesAssessment
            );
            $providerOptions = $this->providerResolver->buildProviderOptions($providerKey->provider, false);

            $response = $this->structuredClassifierClient->execute(
                provider: $provider->value,
                model: $model,
                apiKey: $providerKey->encrypted_key,
                schema: $this->schemaBuilder->build(),
                systemPrompt: $systemPrompt,
                userPrompt: $userPrompt,
                providerOptions: $providerOptions,
            );
        } catch (Throwable $throwable) {
            Log::warning('Command input LLM classification failed, proceeding with deterministic rules only', [
                'command_run_id' => $commandRun->id,
                'error_class' => $throwable::class,
            ]);

            return null;
        }

        $payload = null;
        if (is_array($response->structured)) {
            $payload = [];

            foreach ($response->structured as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $payload[$key] = $value;
            }
        }

        return $this->resultMapper->map($payload);
    }
}
