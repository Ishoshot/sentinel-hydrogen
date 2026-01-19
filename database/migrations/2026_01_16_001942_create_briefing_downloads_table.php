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
        Schema::create('briefing_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('briefing_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 20);
            $table->string('source', 50);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at');

            $table->index(['briefing_generation_id', 'downloaded_at']);
            $table->index(['workspace_id', 'downloaded_at']);
            $table->index(['user_id', 'downloaded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefing_downloads');
    }
};
