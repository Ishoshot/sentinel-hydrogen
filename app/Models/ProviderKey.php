<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiProvider;
use Database\Factories\ProviderKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BYOK (Bring Your Own Key) provider key for a repository.
 *
 * @property int $id
 * @property int $repository_id
 * @property int $workspace_id
 * @property AiProvider $provider
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
