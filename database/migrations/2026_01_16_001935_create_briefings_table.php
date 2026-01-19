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
        Schema::create('briefings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->jsonb('target_roles')->nullable();
            $table->jsonb('parameter_schema')->nullable();
            $table->string('prompt_path')->nullable();
            $table->boolean('requires_ai')->default(true);
            $table->jsonb('eligible_plan_ids')->nullable();
            $table->unsignedInteger('estimated_duration_seconds')->default(60);
            $table->jsonb('output_formats')->default('["html", "pdf"]');
            $table->boolean('is_schedulable')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
            $table->index(['is_system', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefings');
    }
};
