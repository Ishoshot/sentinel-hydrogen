<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InstallationStatus;
use Database\Factories\InstallationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'connection_id',
        'workspace_id',
        'installation_id',
        'account_type',
        'account_login',
        'account_avatar_url',
        'status',
        'permissions',
        'events',
        'suspended_at',
    ];

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /**
     * Check if the installation is active and usable.
     */
    public function isActive(): bool
    {
        return $this->status === InstallationStatus::Active;
    }

    /**
     * Check if this is an organization installation.
     */
    public function isOrganization(): bool
    {
        return $this->account_type === 'Organization';
    }

    /**
     * Check if this is a user installation.
     */
    public function isUser(): bool
    {
        return $this->account_type === 'User';
    }

    /**
     * @param  Builder<Installation>  $query
     * @return Builder<Installation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', InstallationStatus::Active);
    }

    /**
     * @param  Builder<Installation>  $query
     * @return Builder<Installation>
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
            'installation_id' => 'integer',
            'status' => InstallationStatus::class,
            'permissions' => 'array',
            'events' => 'array',
            'suspended_at' => 'datetime',
        ];
    }
}
