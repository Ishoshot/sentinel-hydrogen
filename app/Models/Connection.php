<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Workspace\ConnectionStatus;
use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'provider_id',
        'status',
        'external_id',
        'metadata',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return HasOne<Installation, $this>
     */
    public function installation(): HasOne
    {
        return $this->hasOne(Installation::class);
    }

    /**
     * Check if the connection is active and usable.
     */
    public function isActive(): bool
    {
        return $this->status === ConnectionStatus::Active;
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ConnectionStatus::Active);
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
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
            'status' => ConnectionStatus::class,
            'metadata' => 'array',
        ];
    }
}
