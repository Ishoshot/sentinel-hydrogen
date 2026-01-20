<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * Service for generating vector embeddings using Prism.
 */
final class EmbeddingService implements EmbeddingServiceContract
{
    private const string PROVIDER = 'openai';

    private const string MODEL = 'text-embedding-3-small'; // 1536 dimensions

    private const int BATCH_SIZE = 100;

    /**
     * Generate embeddings for a list of text inputs.
     *
     * @param  array<int, string>  $inputs  The text inputs to generate embeddings for
     * @return array<int, array<int, float>> The embeddings (array of vectors)
     */
    public function generateEmbeddings(array $inputs): array
    {
        if ($inputs === []) {
            return [];
        }

        $embeddings = [];
        $batches = array_chunk($inputs, self::BATCH_SIZE, true);

        foreach ($batches as $batch) {
            $batchEmbeddings = $this->generateBatchEmbeddings(array_values($batch));
            $embeddings = array_merge($embeddings, $batchEmbeddings);
        }

        return $embeddings;
    }

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $input  The text input
     * @return array<int, float> The embedding vector
     */
    public function generateEmbedding(string $input): array
    {
        $embeddings = $this->generateEmbeddings([$input]);

        return $embeddings[0] ?? [];
    }

    /**
     * Generate embeddings for a batch of inputs.
     *
     * @param  array<int, string>  $inputs
     * @return array<int, array<int, float>>
     */
    private function generateBatchEmbeddings(array $inputs): array
    {
        try {
            $prism = Prism::embeddings()
                ->using(self::PROVIDER, self::MODEL);

            // Add each input
            foreach ($inputs as $input) {
                $prism = $prism->fromInput($input);
            }

            $response = $prism->asEmbeddings();

            $embeddings = [];
            /** @var iterable<object{embedding: array<int, float>}> $responseEmbeddings */
            $responseEmbeddings = $response->embeddings;
            foreach ($responseEmbeddings as $embedding) {
                $embeddings[] = $embedding->embedding;
            }

            $tokensUsed = (int) $response->usage->tokens;

            Log::debug('Generated embeddings batch', [
                'count' => count($embeddings),
                'tokens' => $tokensUsed,
            ]);

            return $embeddings;
        } catch (Throwable $throwable) {
            Log::error('Failed to generate embeddings', [
                'error' => $throwable->getMessage(),
                'inputs_count' => count($inputs),
            ]);

            // Return empty embeddings on failure
            return array_fill(0, count($inputs), []);
        }
    }
}
