<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeEmbedding;
use App\Models\CodeIndex;
use App\Models\Repository;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeEmbedding>
 */
final class CodeEmbeddingFactory extends Factory
{
    /**
     * @use RefreshOnCreate<CodeEmbedding>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codeIndex = CodeIndex::factory();

        return [
            'code_index_id' => $codeIndex,
            'repository_id' => Repository::factory(),
            'chunk_type' => ChunkType::File,
            'symbol_name' => null,
            'content' => fake()->text(300),
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Set the chunk type.
     */
    public function ofType(ChunkType $chunkType): static
    {
        return $this->state(fn (array $attributes): array => [
            'chunk_type' => $chunkType,
        ]);
    }

    /**
     * Set the symbol name.
     */
    public function withSymbol(string $symbolName): static
    {
        return $this->state(fn (array $attributes): array => [
            'symbol_name' => $symbolName,
        ]);
    }

    /**
     * Set the code index for the embedding.
     */
    public function forCodeIndex(CodeIndex $codeIndex): static
    {
        return $this->state(fn (array $attributes): array => [
            'code_index_id' => $codeIndex->id,
            'repository_id' => $codeIndex->repository_id,
        ]);
    }

    /**
     * Set the repository for the embedding.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes): array => [
            'repository_id' => $repository->id,
        ]);
    }
}
