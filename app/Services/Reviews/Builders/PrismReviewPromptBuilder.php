<?php

declare(strict_types=1);

namespace App\Services\Reviews\Builders;

use App\Enums\AI\AiProvider;
use App\Enums\AI\TokenCountMode;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\Filters\TokenLimitFilter;
use App\Services\Context\TokenCounting\TokenCounterContext;
use App\Services\Reviews\ReviewPromptBuilder;
use App\Services\Reviews\ValueObjects\ModelLimits;

final readonly class PrismReviewPromptBuilder
{
    private const int DEFAULT_OUTPUT_TOKENS = 8192;

    private const int MIN_CONTEXT_TOKENS = 8000;

    private const int SAFETY_MARGIN_TOKENS = 500;

    /**
     * Create a new instance.
     */
    public function __construct(
        private ReviewPromptBuilder $promptBuilder,
        private TokenLimitFilter $tokenLimitFilter,
        private TokenCounter $tokenCounter,
    ) {}

    /**
     * @param  array<string, mixed>  $policySnapshot
     * @return array{system_prompt: string, user_prompt: string, output_budget: int}
     */
    public function prepare(
        ContextBag $bag,
        array $policySnapshot,
        AiProvider $aiProvider,
        string $model,
        ModelLimits $limits,
        string $apiKey,
    ): array {
        $bag->metadata['token_counter_provider'] = $aiProvider->value;
        $bag->metadata['token_counter_model'] = $model;

        $tokenCounterContext = new TokenCounterContext($aiProvider, $model);
        $outputBudget = $this->resolveOutputBudget($limits);
        $baseSystemPrompt = $this->promptBuilder->buildSystemPrompt($policySnapshot);
        $contextBudget = $this->resolveContextBudget($limits, $baseSystemPrompt, $outputBudget, $tokenCounterContext);

        $bag->metadata['context_token_budget'] = $contextBudget;
        $bag->metadata['output_token_budget'] = $outputBudget;
        $this->tokenLimitFilter->filter($bag);

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($policySnapshot, $bag->guidelines);
        $finalContextBudget = $this->resolveContextBudget($limits, $systemPrompt, $outputBudget, $tokenCounterContext);

        if ($finalContextBudget < $contextBudget) {
            $bag->metadata['context_token_budget'] = $finalContextBudget;
            $this->tokenLimitFilter->filter($bag);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($policySnapshot, $bag->guidelines);
        }

        $userPrompt = $this->promptBuilder->buildUserPromptFromBag($bag);
        $maxInputTokens = $limits->contextWindowTokens - $outputBudget - self::SAFETY_MARGIN_TOKENS;
        $preciseContext = $tokenCounterContext->withMode(TokenCountMode::Precise, $apiKey);
        $promptTokens = $this->tokenCounter->countMessageTokens($systemPrompt, $userPrompt, $preciseContext);

        if ($promptTokens > $maxInputTokens) {
            $overage = $promptTokens - $maxInputTokens;
            $bag->metadata['context_token_budget'] = max($finalContextBudget - $overage, self::MIN_CONTEXT_TOKENS);
            $this->tokenLimitFilter->filter($bag);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($policySnapshot, $bag->guidelines);
            $userPrompt = $this->promptBuilder->buildUserPromptFromBag($bag);
        }

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'output_budget' => $outputBudget,
        ];
    }

    /**
     * Resolve output token budget for the model.
     */
    private function resolveOutputBudget(ModelLimits $limits): int
    {
        return min(self::DEFAULT_OUTPUT_TOKENS, $limits->maxOutputTokens);
    }

    /**
     * Resolve input context budget after system prompt and safety margins.
     */
    private function resolveContextBudget(
        ModelLimits $limits,
        string $systemPrompt,
        int $outputBudget,
        TokenCounterContext $tokenCounterContext
    ): int {
        $systemTokens = $this->tokenCounter->countTextTokens($systemPrompt, $tokenCounterContext);
        $budget = $limits->contextWindowTokens - $systemTokens - $outputBudget - self::SAFETY_MARGIN_TOKENS;

        return max($budget, self::MIN_CONTEXT_TOKENS);
    }
}
