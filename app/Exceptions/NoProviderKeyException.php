<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when a review cannot proceed due to missing BYOK provider keys.
 *
 * This is not an error condition - it simply means the repository
 * has not configured any AI provider keys in .sentinel/config.yaml.
 */
final class NoProviderKeyException extends Exception
{
    /**
     * Create exception for no configured providers.
     */
    public static function noProvidersConfigured(): self
    {
        return new self('No provider keys configured for this repository');
    }

    /**
     * Create exception for a specific provider not having a key.
     */
    public static function forProvider(string $providerName): self
    {
        return new self(sprintf('No API key configured for provider: %s', $providerName));
    }
}
