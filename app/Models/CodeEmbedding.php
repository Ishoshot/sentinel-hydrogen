<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CodeIndexing\ChunkType;
use Database\Factories\CodeEmbeddingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ChunkType $chunk_type
 * @property array<float>|null $embedding
 * @property array<string, mixed>|null $metadata
 */
final class CodeEmbedding extends Model
{
    /** @use HasFactory<CodeEmbeddingFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code_index_id',
        'repository_id',
        'chunk_type',
        'symbol_name',
        'content',
        'embedding',
        'metadata',
        'created_at',
    ];

    /**
     * @return BelongsTo<CodeIndex, $this>
     */
    public function codeIndex(): BelongsTo
    {
        return $this->belongsTo(CodeIndex::class);
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @param  Builder<CodeEmbedding>  $query
     * @return Builder<CodeEmbedding>
     */
    public function scopeForRepository(Builder $query, Repository $repository): Builder
    {
        return $query->where('repository_id', $repository->id);
    }

    /**
     * @param  Builder<CodeEmbedding>  $query
     * @return Builder<CodeEmbedding>
     */
    public function scopeOfChunkType(Builder $query, ChunkType $chunkType): Builder
    {
        return $query->where('chunk_type', $chunkType->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_type' => ChunkType::class,
            'metadata' => 'array',
        ];
    }
}
