<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use Carbon\Carbon;
use Database\Factories\RepositorySettingsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $repository_id
 * @property int $workspace_id
 * @property bool $auto_review_enabled
 * @property array<string, mixed>|null $review_rules
 * @property array<string, mixed>|null $sentinel_config
 * @property Carbon|null $config_synced_at
 * @property string|null $config_error
 */
final class RepositorySettings extends Model
{
    /** @use HasFactory<RepositorySettingsFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'workspace_id',
        'auto_review_enabled',
        'review_rules',
        'sentinel_config',
        'config_synced_at',
        'config_error',
    ];

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @param  Builder<RepositorySettings>  $query
     * @return Builder<RepositorySettings>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Get the parsed SentinelConfig DTO.
     */
    public function getSentinelConfigDto(): ?SentinelConfig
    {
        if ($this->sentinel_config === null) {
            return null;
        }

        return SentinelConfig::fromArray($this->sentinel_config);
    }

    /**
     * Get config with defaults if not set.
     */
    public function getConfigOrDefault(): SentinelConfig
    {
        return $this->getSentinelConfigDto() ?? SentinelConfig::default();
    }

    /**
     * Check if there is a config error.
     */
    public function hasConfigError(): bool
    {
        return $this->config_error !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_review_enabled' => 'boolean',
            'review_rules' => 'array',
            'sentinel_config' => 'array',
            'config_synced_at' => 'datetime',
        ];
    }
}
