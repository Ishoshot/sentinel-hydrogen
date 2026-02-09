<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AI\AiProvider;
use Database\Factories\ProviderKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BYOK (Bring Your Own Key) provider key for a repository.
 *
 * @property int $id
 * @property int|null $repository_id
 * @property int $workspace_id
 * @property AiProvider $provider
 * @property int|null $provider_model_id
 * @property string $encrypted_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class ProviderKey extends Model
{
    /** @use HasFactory<ProviderKeyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'workspace_id',
        'provider',
        'provider_model_id',
        'encrypted_key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'encrypted_key',
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
     * @return BelongsTo<AiOption, $this>
     */
    public function providerModel(): BelongsTo
    {
        return $this->belongsTo(AiOption::class, 'provider_model_id');
    }

    /**
     * Get the model identifier if a model is selected.
     */
    public function getModelIdentifier(): ?string
    {
        return $this->providerModel?->identifier;
    }

    /**
     * Scope to workspace-level keys (no repository).
     *
     * @param  Builder<ProviderKey>  $query
     * @return Builder<ProviderKey>
     */
    public function scopeWorkspaceLevel(Builder $query): Builder
    {
        return $query->whereNull('repository_id');
    }

    /**
     * Check if this key is a workspace-level key (not tied to a repository).
     */
    public function isWorkspaceLevel(): bool
    {
        return $this->repository_id === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'encrypted_key' => 'encrypted',
        ];
    }
}
