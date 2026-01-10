<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'installation_id',
        'workspace_id',
        'github_id',
        'name',
        'full_name',
        'private',
        'default_branch',
        'language',
        'description',
    ];

    /**
     * @return BelongsTo<Installation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasOne<RepositorySettings, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(RepositorySettings::class);
    }

    /**
     * @return HasMany<Run, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    /**
     * Get the owner part of the full_name (e.g., "owner" from "owner/repo").
     */
    public function getOwnerAttribute(): string
    {
        return explode('/', $this->full_name)[0];
    }

    /**
     * Check if auto-review is enabled for this repository.
     */
    public function hasAutoReviewEnabled(): bool
    {
        return $this->settings->auto_review_enabled ?? true;
    }

    /**
     * @param  Builder<Repository>  $query
     * @return Builder<Repository>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * @param  Builder<Repository>  $query
     * @return Builder<Repository>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('private', false);
    }

    /**
     * @param  Builder<Repository>  $query
     * @return Builder<Repository>
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('private', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'private' => 'boolean',
        ];
    }
}
