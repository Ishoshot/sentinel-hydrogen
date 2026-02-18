<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\ValueObjects;

use App\Enums\CodeIndexing\CodeIndexScopeType;

final readonly class CodeIndexScope
{
    /**
     * Create a code-index scope value object.
     */
    public function __construct(
        public CodeIndexScopeType $type,
        public string $ref,
        public ?int $pullRequestNumber = null,
        public ?string $headSha = null,
    ) {}

    /**
     * Create a baseline (default branch) indexing scope.
     */
    public static function baseline(): self
    {
        return new self(
            type: CodeIndexScopeType::Baseline,
            ref: CodeIndexScopeType::Baseline->value,
        );
    }

    /**
     * Create a pull-request-specific indexing scope.
     */
    public static function pullRequest(int $pullRequestNumber, string $headSha): self
    {
        return new self(
            type: CodeIndexScopeType::PullRequest,
            ref: sprintf('pr:%d@%s', $pullRequestNumber, $headSha),
            pullRequestNumber: $pullRequestNumber,
            headSha: $headSha,
        );
    }

    /**
     * @param  array{scope_type?: string, scope_ref?: string, pull_request_number?: int|null, head_sha?: string|null}|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if (! is_array($data)) {
            return self::baseline();
        }

        $scopeType = is_string($data['scope_type'] ?? null)
            ? CodeIndexScopeType::tryFrom($data['scope_type'])
            : null;

        if ($scopeType === null || $scopeType === CodeIndexScopeType::Baseline) {
            return self::baseline();
        }

        $pullRequestNumber = is_int($data['pull_request_number'] ?? null)
            ? $data['pull_request_number']
            : null;

        $headSha = is_string($data['head_sha'] ?? null) && $data['head_sha'] !== ''
            ? $data['head_sha']
            : null;

        if ($pullRequestNumber === null || $headSha === null) {
            return self::baseline();
        }

        return self::pullRequest($pullRequestNumber, $headSha);
    }

    /**
     * @return array{scope_type: string, scope_ref: string, pull_request_number: int|null, head_sha: string|null}
     */
    public function toArray(): array
    {
        return [
            'scope_type' => $this->type->value,
            'scope_ref' => $this->ref,
            'pull_request_number' => $this->pullRequestNumber,
            'head_sha' => $this->headSha,
        ];
    }

    /**
     * Determine whether this scope targets pull-request index data.
     */
    public function isPullRequest(): bool
    {
        return $this->type === CodeIndexScopeType::PullRequest;
    }
}
