<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CodeIndex;
use App\Models\Repository;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeIndex>
 */
final class CodeIndexFactory extends Factory
{
    /**
     * @use RefreshOnCreate<CodeIndex>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'commit_sha' => fake()->sha1(),
            'file_path' => sprintf('app/Models/%s.php', fake()->word()),
            'file_type' => 'php',
            'content' => fake()->text(500),
            'structure' => null,
            'metadata' => null,
            'indexed_at' => now(),
            'created_at' => now(),
        ];
    }

    /**
     * Set a specific file path for the index.
     */
    public function withFilePath(string $filePath): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_path' => $filePath,
            'file_type' => pathinfo($filePath, PATHINFO_EXTENSION) ?: 'txt',
        ]);
    }

    /**
     * Set the repository for the index.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes): array => [
            'repository_id' => $repository->id,
        ]);
    }

    /**
     * Set the commit SHA for the index.
     */
    public function forCommit(string $commitSha): static
    {
        return $this->state(fn (array $attributes): array => [
            'commit_sha' => $commitSha,
        ]);
    }

    /**
     * Set structure data (AST analysis).
     *
     * @param  array<string, mixed>  $structure
     */
    public function withStructure(array $structure): static
    {
        return $this->state(fn (array $attributes): array => [
            'structure' => $structure,
        ]);
    }
}
