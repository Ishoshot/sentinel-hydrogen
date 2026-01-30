<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Commands\CommandRunStatus;
use App\Enums\Commands\CommandType;
use Database\Factories\CommandRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CommandType $command_type
 * @property CommandRunStatus $status
 * @property array<string, mixed>|null $response
 * @property array<string, mixed>|null $context_snapshot
 * @property array<string, mixed>|null $metrics
 * @property array<string, mixed>|null $metadata
 */
final class CommandRun extends Model
{
    /** @use HasFactory<CommandRunFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'repository_id',
        'initiated_by_id',
        'external_reference',
        'github_comment_id',
        'issue_number',
        'is_pull_request',
        'command_type',
        'query',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'response',
        'context_snapshot',
        'metrics',
        'metadata',
        'created_at',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_id');
    }

    /**
     * @param  Builder<CommandRun>  $query
     * @return Builder<CommandRun>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * @param  Builder<CommandRun>  $query
     * @return Builder<CommandRun>
     */
    public function scopeForRepository(Builder $query, Repository $repository): Builder
    {
        return $query->where('repository_id', $repository->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'command_type' => CommandType::class,
            'status' => CommandRunStatus::class,
            'is_pull_request' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'response' => 'array',
            'context_snapshot' => 'array',
            'metrics' => 'array',
            'metadata' => 'array',
        ];
    }
}
