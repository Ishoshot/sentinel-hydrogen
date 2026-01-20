<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Contracts;

interface EmbeddingServiceContract
{
    /**
     * Generate embeddings for a list of text inputs.
     *
     * @param  array<int, string>  $inputs  The text inputs to generate embeddings for
     * @return array<int, array<int, float>> The embeddings (array of vectors)
     */
    public function generateEmbeddings(array $inputs): array;

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $input  The text input
     * @return array<int, float> The embedding vector
     */
    public function generateEmbedding(string $input): array;
}
