<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Support\PathRuleMatcher;

/**
 * Resolves inclusion decisions for configured path rules.
 */
final readonly class ConfiguredPathInclusionDecider
{
    /**
     * Create a new path inclusion decider instance.
     */
    public function __construct(private PathRuleMatcher $matcher) {}

    /**
     * Determine whether a path should remain in context.
     */
    public function shouldInclude(string $path, PathsConfig $pathsConfig): bool
    {
        if ($pathsConfig->ignore !== [] && $this->matcher->matchesAny($path, $pathsConfig->ignore)) {
            return false;
        }

        if ($pathsConfig->include !== [] && ! $this->matcher->matchesAny($path, $pathsConfig->include)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether a path matches sensitive patterns.
     */
    public function isSensitive(string $path, PathsConfig $pathsConfig): bool
    {
        if ($pathsConfig->sensitive === []) {
            return false;
        }

        return $this->matcher->matchesAny($path, $pathsConfig->sensitive);
    }
}
