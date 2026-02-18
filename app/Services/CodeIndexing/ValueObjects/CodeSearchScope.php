<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\ValueObjects;

final readonly class CodeSearchScope
{
    /**
     * Create a code-search scope.
     */
    public function __construct(
        public ?CodeIndexScope $pullRequestScope = null,
        public bool $includeBaseline = true,
    ) {}

    /**
     * Create a baseline-only search scope.
     */
    public static function baseline(): self
    {
        return new self;
    }

    /**
     * Create a search scope that targets PR indexes and optionally baseline fallback.
     */
    public static function forPullRequest(int $pullRequestNumber, string $headSha, bool $includeBaseline = true): self
    {
        return new self(
            pullRequestScope: CodeIndexScope::pullRequest($pullRequestNumber, $headSha),
            includeBaseline: $includeBaseline,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function fromRunMetadata(?array $metadata, bool $includeBaseline = true): self
    {
        if (! is_array($metadata)) {
            return self::baseline();
        }

        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null)
            ? $metadata['pull_request_number']
            : null;

        $headSha = is_string($metadata['head_sha'] ?? null) && $metadata['head_sha'] !== ''
            ? $metadata['head_sha']
            : null;

        if ($pullRequestNumber === null || $pullRequestNumber <= 0 || $headSha === null) {
            return self::baseline();
        }

        return self::forPullRequest($pullRequestNumber, $headSha, $includeBaseline);
    }

    /**
     * Determine whether pull-request-scoped search should be used.
     */
    public function hasPullRequestScope(): bool
    {
        return $this->pullRequestScope instanceof CodeIndexScope && $this->pullRequestScope->isPullRequest();
    }

    /**
     * Build a deterministic cache-key suffix for this search scope.
     */
    public function cacheKeySuffix(): string
    {
        if (! $this->hasPullRequestScope()) {
            return 'baseline';
        }

        $pullRequestScope = $this->pullRequestScope;

        if (! $pullRequestScope instanceof CodeIndexScope) {
            return 'baseline';
        }

        return sprintf(
            'pr:%d:%s:%s',
            $pullRequestScope->pullRequestNumber,
            $pullRequestScope->headSha,
            $this->includeBaseline ? '1' : '0',
        );
    }
}
