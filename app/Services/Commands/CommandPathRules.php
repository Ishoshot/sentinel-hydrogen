<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\SensitiveDataRedactor;
use App\Support\PathRuleMatcher;

/**
 * Applies path and sensitive-data rules for command tooling.
 */
final readonly class CommandPathRules
{
    /**
     * Create a new CommandPathRules instance.
     */
    public function __construct(
        private PathsConfig $pathsConfig,
        private SensitiveDataRedactor $redactor,
        private PathRuleMatcher $matcher,
    ) {}

    /**
     * Determine whether a path should be included.
     */
    public function shouldIncludePath(string $path): bool
    {
        if ($this->pathsConfig->ignore !== [] && $this->matcher->matchesAny($path, $this->pathsConfig->ignore)) {
            return false;
        }

        if ($this->pathsConfig->include !== [] && ! $this->matcher->matchesAny($path, $this->pathsConfig->include)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether a path should be treated as sensitive.
     */
    public function isSensitivePath(string $path): bool
    {
        if ($this->pathsConfig->sensitive !== [] && $this->matcher->matchesAny($path, $this->pathsConfig->sensitive)) {
            return true;
        }

        return $this->redactor->isSensitiveFile($path);
    }

    /**
     * Redact sensitive data patterns from text.
     */
    public function redact(string $text): string
    {
        return $this->redactor->redact($text);
    }

    /**
     * Sanitize content for a specific path.
     */
    public function sanitizeContentForPath(string $path, string $content): string
    {
        if ($this->isSensitivePath($path)) {
            return '[REDACTED - sensitive file]';
        }

        return $this->redact($content);
    }

    /**
     * Filter a list of paths to those allowed by the policy.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function filterPaths(array $paths): array
    {
        return array_values(array_filter(
            $paths,
            $this->shouldIncludePath(...)
        ));
    }
}
