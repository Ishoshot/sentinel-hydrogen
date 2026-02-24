<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\ValueObjects;

/**
 * Typed result of fetching .sentinel/config.yaml from a repository.
 */
final readonly class ConfigFetchResult
{
    /**
     * Create a new config fetch result.
     */
    public function __construct(
        public bool $found,
        public ?string $content,
        public ?string $sha,
        public ?string $error,
    ) {}

    /**
     * Create a result representing a successfully found config file.
     */
    public static function found(string $content, string $sha): self
    {
        return new self(
            found: true,
            content: $content,
            sha: $sha,
            error: null,
        );
    }

    /**
     * Create a result representing a config file that was not found (404).
     */
    public static function notFound(): self
    {
        return new self(
            found: false,
            content: null,
            sha: null,
            error: null,
        );
    }

    /**
     * Create a result representing a failed fetch attempt.
     */
    public static function failed(string $error): self
    {
        return new self(
            found: false,
            content: null,
            sha: null,
            error: $error,
        );
    }

    /**
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'content' => $this->content,
            'sha' => $this->sha,
            'error' => $this->error,
        ];
    }
}
