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
        Schema::create('briefing_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('briefing_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->noActionOnDelete();
            $table->string('token', 64)->unique();
            $table->string('password_hash')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->unsignedInteger('max_accesses')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at');

            $table->index(['briefing_generation_id']);
            $table->index(['expires_at', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefing_shares');
    }
};
