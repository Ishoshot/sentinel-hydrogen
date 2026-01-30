<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

/**
 * Metadata about the pull request context for a command execution.
 */
final readonly class PullRequestMetadata
{
    /**
     * Create a new PullRequestMetadata instance.
     */
    public function __construct(
        public ?string $prTitle = null,
        public ?int $prAdditions = null,
        public ?int $prDeletions = null,
        public ?int $prChangedFiles = null,
        public ?bool $prContextIncluded = null,
        public ?string $baseBranch = null,
        public ?string $headBranch = null,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool, base_branch?: string, head_branch?: string}|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            prTitle: $data['pr_title'] ?? null,
            prAdditions: $data['pr_additions'] ?? null,
            prDeletions: $data['pr_deletions'] ?? null,
            prChangedFiles: $data['pr_changed_files'] ?? null,
            prContextIncluded: $data['pr_context_included'] ?? null,
            baseBranch: $data['base_branch'] ?? null,
            headBranch: $data['head_branch'] ?? null,
        );
    }

    /**
     * Check if PR context was included in the command.
     */
    public function hasContext(): bool
    {
        return $this->prContextIncluded === true;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool, base_branch?: string, head_branch?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'pr_title' => $this->prTitle,
            'pr_additions' => $this->prAdditions,
            'pr_deletions' => $this->prDeletions,
            'pr_changed_files' => $this->prChangedFiles,
            'pr_context_included' => $this->prContextIncluded,
            'base_branch' => $this->baseBranch,
            'head_branch' => $this->headBranch,
        ], fn (mixed $v): bool => $v !== null);
    }
}
