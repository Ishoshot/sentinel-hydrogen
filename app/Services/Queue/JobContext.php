<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Enums\Queue;
use App\Models\Workspace;

/**
 * Context object containing all signals for queue resolution.
 *
 * This immutable value object carries information about the job being dispatched
 * that rules can use to determine the appropriate queue.
 */
final readonly class JobContext
{
    /**
     * @param  class-string  $jobClass  The fully qualified class name of the job
     * @param  string|null  $tier  The workspace tier (free, paid, enterprise)
     * @param  int|null  $workspaceId  The workspace ID for scoping
     * @param  bool  $isSystemJob  Whether this is a system/internal job
     * @param  bool  $isUserInitiated  Whether the job was triggered by a user action
     * @param  string  $importance  Job importance level (critical, high, normal, low)
     * @param  int|null  $estimatedDurationSeconds  Estimated job duration in seconds
     * @param  array<string, mixed>  $metadata  Additional context metadata
     */
    public function __construct(
        public string $jobClass,
        public ?string $tier = null,
        public ?int $workspaceId = null,
        public bool $isSystemJob = false,
        public bool $isUserInitiated = false,
        public string $importance = 'normal',
        public ?int $estimatedDurationSeconds = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a context from a workspace.
     *
     * @param  class-string  $jobClass
     * @param  array<string, mixed>  $metadata
     */
    public static function forWorkspace(
        string $jobClass,
        Workspace $workspace,
        bool $isUserInitiated = false,
        string $importance = 'normal',
        ?int $estimatedDurationSeconds = null,
        array $metadata = [],
    ): self {
        return new self(
            jobClass: $jobClass,
            tier: $workspace->getCurrentTier(),
            workspaceId: $workspace->id,
            isSystemJob: false,
            isUserInitiated: $isUserInitiated,
            importance: $importance,
            estimatedDurationSeconds: $estimatedDurationSeconds,
            metadata: $metadata,
        );
    }

    /**
     * Create a context for a system job.
     *
     * @param  class-string  $jobClass
     * @param  array<string, mixed>  $metadata
     */
    public static function forSystemJob(
        string $jobClass,
        string $importance = 'critical',
        ?int $estimatedDurationSeconds = null,
        array $metadata = [],
    ): self {
        return new self(
            jobClass: $jobClass,
            tier: null,
            workspaceId: null,
            isSystemJob: true,
            isUserInitiated: false,
            importance: $importance,
            estimatedDurationSeconds: $estimatedDurationSeconds,
            metadata: $metadata,
        );
    }

    /**
     * Create a context for a webhook job.
     *
     * @param  class-string  $jobClass
     * @param  array<string, mixed>  $metadata
     */
    public static function forWebhook(
        string $jobClass,
        ?int $workspaceId = null,
        array $metadata = [],
    ): self {
        return new self(
            jobClass: $jobClass,
            tier: null,
            workspaceId: $workspaceId,
            isSystemJob: false,
            isUserInitiated: false,
            importance: 'high',
            estimatedDurationSeconds: 30,
            metadata: array_merge(['source' => 'webhook'], $metadata),
        );
    }

    /**
     * Check if the job is from a paid tier.
     */
    public function isPaidTier(): bool
    {
        return in_array($this->tier, ['paid', 'pro', 'team', 'enterprise'], true);
    }

    /**
     * Check if the job is from enterprise tier.
     */
    public function isEnterpriseTier(): bool
    {
        return $this->tier === 'enterprise';
    }

    /**
     * Check if the job is critical importance.
     */
    public function isCritical(): bool
    {
        return $this->importance === 'critical';
    }

    /**
     * Check if the job is expected to be long-running.
     */
    public function isLongRunning(): bool
    {
        return $this->estimatedDurationSeconds !== null && $this->estimatedDurationSeconds > 120;
    }

    /**
     * Get the default queue for this context.
     */
    public function getDefaultQueue(): Queue
    {
        if ($this->isSystemJob) {
            return Queue::System;
        }

        return Queue::Default;
    }

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Create a copy with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            jobClass: $this->jobClass,
            tier: $this->tier,
            workspaceId: $this->workspaceId,
            isSystemJob: $this->isSystemJob,
            isUserInitiated: $this->isUserInitiated,
            importance: $this->importance,
            estimatedDurationSeconds: $this->estimatedDurationSeconds,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Convert to array for debugging/logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'job_class' => $this->jobClass,
            'tier' => $this->tier,
            'workspace_id' => $this->workspaceId,
            'is_system_job' => $this->isSystemJob,
            'is_user_initiated' => $this->isUserInitiated,
            'importance' => $this->importance,
            'estimated_duration_seconds' => $this->estimatedDurationSeconds,
            'metadata' => $this->metadata,
        ];
    }
}
