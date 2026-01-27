<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPostgres = Schema::getConnection()->getDriverName() === 'pgsql';

        Schema::create('code_embeddings', function (Blueprint $table) use ($isPostgres): void {
            $table->id();
            $table->foreignId('code_index_id')->constrained('code_indexes')->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained('repositories')->cascadeOnDelete();
            $table->string('chunk_type', 50);
            $table->string('symbol_name')->nullable();
            $table->text('content');

            if ($isPostgres) {
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }

            $table->timestamp('created_at');

            $table->index(['repository_id', 'chunk_type']);
            $table->index(['code_index_id']);
            $table->index(['symbol_name']);
        });

        if ($isPostgres) {
            // Add vector column for embeddings (1536 dimensions for OpenAI text-embedding-3-small)
            Schema::getConnection()->statement('ALTER TABLE code_embeddings ADD COLUMN embedding vector(1536)');

            // Create HNSW index for fast similarity search with cosine distance
            Schema::getConnection()->statement('CREATE INDEX code_embeddings_embedding_idx ON code_embeddings USING hnsw (embedding vector_cosine_ops)');
        } else {
            // For SQLite testing: add a text column to store serialized embedding
            Schema::table('code_embeddings', function (Blueprint $table): void {
                $table->text('embedding')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_embeddings');
    }
};
