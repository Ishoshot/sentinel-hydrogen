<?php

declare(strict_types=1);

namespace App\Services\Reviews\Strategies;

use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes a review across providers with fallback retry support.
 */
final readonly class PrismProviderFallbackStrategy
{
    private const int MAX_FALLBACK_ATTEMPTS = 3;

    /**
     * Execute a review attempt across providers with fallback support.
     *
     * @param  array<AiProvider>  $providersToTry
     * @param  Closure(AiProvider): ReviewResult  $executeAttempt
     *
     * @throws NoProviderKeyException When no providers succeed
     * @throws Throwable When a provider fails and fallback is disabled
     */
    public function execute(array $providersToTry, ProviderConfig $providerConfig, Closure $executeAttempt): ReviewResult
    {
        $attempts = 0;
        $maxAttempts = $providerConfig->fallback ? min(count($providersToTry), self::MAX_FALLBACK_ATTEMPTS) : 1;

        /** @var Throwable|null $lastException */
        $lastException = null;

        foreach ($providersToTry as $aiProvider) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            $attempts++;

            try {
                return $executeAttempt($aiProvider);
            } catch (NoProviderKeyException $e) {
                $lastException = $e;
                Log::warning('Provider key not available, trying fallback', [
                    'provider' => $aiProvider->value,
                    'attempt' => $attempts,
                    'fallback_enabled' => $providerConfig->fallback,
                ]);

                if (! $providerConfig->fallback) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $lastException = $e;
                Log::warning('Provider failed, trying fallback', [
                    'provider' => $aiProvider->value,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                    'fallback_enabled' => $providerConfig->fallback,
                ]);

                if (! $providerConfig->fallback) {
                    throw $e;
                }
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw NoProviderKeyException::noProvidersConfigured();
    }
}
