<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_id')->unique()->constrained()->noActionOnDelete();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->boolean('auto_review_enabled')->default(true);
            $table->json('review_rules')->nullable();
            $table->timestamps();

            $table->index(['workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_settings');
    }
};
