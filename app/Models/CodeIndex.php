<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CodeIndexFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $structure
 * @property array<string, mixed>|null $metadata
 */
final class CodeIndex extends Model
{
    /** @use HasFactory<CodeIndexFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     */
    protected $table = 'code_indexes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'commit_sha',
        'file_path',
        'file_type',
        'content',
        'structure',
        'metadata',
        'indexed_at',
        'created_at',
    ];

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return HasMany<CodeEmbedding, $this>
     */
    public function embeddings(): HasMany
    {
        return $this->hasMany(CodeEmbedding::class);
    }

    /**
     * @param  Builder<CodeIndex>  $query
     * @return Builder<CodeIndex>
     */
    public function scopeForRepository(Builder $query, Repository $repository): Builder
    {
        return $query->where('repository_id', $repository->id);
    }

    /**
     * @param  Builder<CodeIndex>  $query
     * @return Builder<CodeIndex>
     */
    public function scopeForCommit(Builder $query, string $commitSha): Builder
    {
        return $query->where('commit_sha', $commitSha);
    }

    /**
     * @param  Builder<CodeIndex>  $query
     * @return Builder<CodeIndex>
     */
    public function scopeForFilePath(Builder $query, string $filePath): Builder
    {
        return $query->where('file_path', $filePath);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }
}
