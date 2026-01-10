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
        Schema::create('runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('external_reference');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->jsonb('policy_snapshot')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['workspace_id', 'created_at']);
            $table->index(['repository_id', 'created_at']);
            $table->index(['status']);
            $table->index(['external_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
