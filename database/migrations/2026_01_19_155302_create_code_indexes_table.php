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

        Schema::create('code_indexes', function (Blueprint $table) use ($isPostgres): void {
            $table->id();
            $table->foreignId('repository_id')->constrained('repositories')->cascadeOnDelete();
            $table->string('commit_sha');
            $table->string('file_path');
            $table->string('file_type', 20);
            $table->text('content');

            if ($isPostgres) {
                $table->jsonb('structure')->nullable();
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('structure')->nullable();
                $table->json('metadata')->nullable();
            }

            $table->timestamp('indexed_at');
            $table->timestamp('created_at');

            $table->index(['repository_id', 'commit_sha']);
            $table->index(['repository_id', 'file_path']);
            $table->unique(['repository_id', 'commit_sha', 'file_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_indexes');
    }
};
