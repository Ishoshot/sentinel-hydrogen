<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AI\AiProvider;
use Database\Factories\AiOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI model configuration for BYOK provider keys.
 *
 * @property int $id
 * @property AiProvider $provider
 * @property string $identifier
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $context_window_tokens
 * @property int|null $max_output_tokens
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AiOption extends Model
{
    /** @use HasFactory<AiOptionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'provider_models';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'identifier',
        'name',
        'description',
        'is_default',
        'is_active',
        'sort_order',
        'context_window_tokens',
        'max_output_tokens',
    ];

    /**
     * Get the default model for a provider.
     */
    public static function getDefault(AiProvider $provider): ?self
    {
        return self::query()
            ->where('provider', $provider)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return HasMany<ProviderKey, $this>
     */
    public function providerKeys(): HasMany
    {
        return $this->hasMany(ProviderKey::class, 'provider_model_id');
    }

    /**
     * Scope to active models only.
     *
     * @param  Builder<AiOption>  $query
     * @return Builder<AiOption>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to models for a specific provider.
     *
     * @param  Builder<AiOption>  $query
     * @return Builder<AiOption>
     */
    public function scopeForProvider(Builder $query, AiProvider $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'context_window_tokens' => 'integer',
            'max_output_tokens' => 'integer',
        ];
    }
}
