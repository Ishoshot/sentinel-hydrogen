<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AI\AiProvider;
use App\Models\AiOption;
use App\Services\Reviews\Contracts\ModelLimitsResolverContract;
use App\Services\Reviews\ValueObjects\ModelLimits;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves model context window and output limits.
 */
final class ModelLimitsResolver implements ModelLimitsResolverContract
{
    private const int DEFAULT_CONTEXT_WINDOW_TOKENS = 32000;

    private const int DEFAULT_MAX_OUTPUT_TOKENS = 4096;

    private const int CACHE_TTL_SECONDS = 3600;

    /**
     * Safe fallback limits per provider/model identifier.
     *
     * @var array<string, array<string, array{context: int, output: int}>>
     */
    private const array FALLBACK_LIMITS = [
        'anthropic' => [
            'claude-sonnet-4-5-20250929' => ['context' => 200000, 'output' => 64000],
            'claude-sonnet-4-20250514' => ['context' => 200000, 'output' => 64000],
            'claude-3-5-haiku-20241022' => ['context' => 200000, 'output' => 8192],
        ],
        'openai' => [
            'gpt-4o' => ['context' => 128000, 'output' => 16384],
            'gpt-4o-mini' => ['context' => 128000, 'output' => 16384],
            'gpt-4-turbo' => ['context' => 128000, 'output' => 4096],
        ],
    ];

    /**
     * Resolve limits for a provider model, falling back to safe defaults.
     */
    public function resolve(AiProvider $provider, string $identifier): ModelLimits
    {
        $cacheKey = sprintf('model_limits:%s:%s', $provider->value, $identifier);

        /** @var ModelLimits $limits */
        $limits = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($provider, $identifier): ModelLimits {
            $option = AiOption::query()
                ->where('provider', $provider)
                ->where('identifier', $identifier)
                ->first();

            if ($option?->context_window_tokens !== null && $option->max_output_tokens !== null) {
                return new ModelLimits($option->context_window_tokens, $option->max_output_tokens);
            }

            return $this->fallback($provider, $identifier);
        });

        return $limits;
    }

    /**
     * Resolve a safe fallback limit for unknown models.
     */
    private function fallback(AiProvider $provider, string $identifier): ModelLimits
    {
        $providerKey = $provider->value;

        if (isset(self::FALLBACK_LIMITS[$providerKey][$identifier])) {
            $limits = self::FALLBACK_LIMITS[$providerKey][$identifier];

            return new ModelLimits($limits['context'], $limits['output']);
        }

        return new ModelLimits(self::DEFAULT_CONTEXT_WINDOW_TOKENS, self::DEFAULT_MAX_OUTPUT_TOKENS);
    }
}
