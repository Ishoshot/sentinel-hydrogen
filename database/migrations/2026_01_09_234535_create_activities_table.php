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
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->string('type');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['workspace_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
