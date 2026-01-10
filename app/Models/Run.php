<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunStatus;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'external_reference',
        'status',
        'started_at',
        'completed_at',
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
