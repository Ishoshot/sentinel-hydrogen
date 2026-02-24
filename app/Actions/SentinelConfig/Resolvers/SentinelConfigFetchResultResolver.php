<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Resolvers;

use App\Actions\SentinelConfig\ValueObjects\ConfigFetchResult;

final class SentinelConfigFetchResultResolver
{
    /**
     * Create a result for a found config file.
     */
    public function found(?string $content, ?string $sha, ?string $error): ConfigFetchResult
    {
        return new ConfigFetchResult(
            found: true,
            content: $content,
            sha: $sha,
            error: $error,
        );
    }

    /**
     * Create a result for a config file that was not found.
     */
    public function notFound(): ConfigFetchResult
    {
        return ConfigFetchResult::notFound();
    }

    /**
     * Create a result for a failed fetch attempt.
     */
    public function failed(string $error): ConfigFetchResult
    {
        return ConfigFetchResult::failed($error);
    }
}
