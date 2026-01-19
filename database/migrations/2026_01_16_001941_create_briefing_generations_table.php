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
        Schema::create('briefing_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->foreignId('briefing_id')->constrained()->noActionOnDelete();
            $table->foreignId('generated_by_id')->constrained('users')->noActionOnDelete();
            $table->jsonb('parameters')->nullable();
            $table->string('status', 50)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('narrative')->nullable();
            $table->jsonb('structured_data')->nullable();
            $table->jsonb('achievements')->nullable();
            $table->jsonb('excerpts')->nullable();
            $table->jsonb('output_paths')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'briefing_id', 'created_at']);
            $table->index(['workspace_id', 'generated_by_id']);
            $table->index(['status']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefing_generations');
    }
};
