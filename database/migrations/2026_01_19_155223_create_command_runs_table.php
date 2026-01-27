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

        Schema::create('command_runs', function (Blueprint $table) use ($isPostgres): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->foreignId('repository_id')->constrained()->noActionOnDelete();
            $table->foreignId('initiated_by_id')->nullable()->constrained('users')->nullOnDelete();

            // GitHub context
            $table->string('external_reference');
            $table->bigInteger('github_comment_id');
            $table->integer('issue_number')->nullable();
            $table->boolean('is_pull_request')->default(false);

            // Command details
            $table->string('command_type');
            $table->text('query');

            // Execution
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();

            // Results
            if ($isPostgres) {
                $table->jsonb('response')->nullable();
                $table->jsonb('context_snapshot')->nullable();
                $table->jsonb('metrics')->nullable();
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('response')->nullable();
                $table->json('context_snapshot')->nullable();
                $table->json('metrics')->nullable();
                $table->json('metadata')->nullable();
            }

            $table->timestamp('created_at');

            $table->index(['workspace_id', 'created_at']);
            $table->index(['repository_id', 'created_at']);
            $table->index(['status']);
            $table->index(['command_type']);
            $table->index(['github_comment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
