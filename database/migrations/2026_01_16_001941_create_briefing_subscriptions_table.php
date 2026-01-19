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
        Schema::create('briefing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('briefing_id')->constrained()->cascadeOnDelete();
            $table->string('schedule_preset', 50);
            $table->unsignedTinyInteger('schedule_day')->nullable();
            $table->unsignedTinyInteger('schedule_hour')->default(9);
            $table->jsonb('parameters')->nullable();
            $table->jsonb('delivery_channels')->default('["push"]');
            $table->text('slack_webhook_url')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('next_scheduled_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
            $table->index(['next_scheduled_at', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->unique(['workspace_id', 'user_id', 'briefing_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefing_subscriptions');
    }
};
