<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Reviews\RunStatus;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RunStatus $status
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $metrics
 * @property array<string, mixed>|null $policy_snapshot
 */
final class Run extends Model
{
    /** @use HasFactory<RunFactory> */
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
        'status',
        'started_at',
        'completed_at',
        'pr_number',
        'pr_title',
        'base_branch',
        'head_branch',
        'duration_seconds',
        'metrics',
        'policy_snapshot',
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
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @param  Builder<Run>  $query
     * @return Builder<Run>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Get the effective PR number from column or metadata.
     */
    public function getEffectivePrNumber(): ?int
    {
        if ($this->pr_number !== null) {
            return $this->pr_number;
        }

        $metadataPrNumber = $this->metadata['pull_request_number'] ?? null;

        return is_numeric($metadataPrNumber) ? (int) $metadataPrNumber : null;
    }

    /**
     * Get the effective PR title from column or metadata.
     */
    public function getEffectivePrTitle(): ?string
    {
        if ($this->pr_title !== null) {
            return $this->pr_title;
        }

        $metadataTitle = $this->metadata['pull_request_title'] ?? null;

        return is_string($metadataTitle) ? $metadataTitle : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metrics' => 'array',
            'policy_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }
}
