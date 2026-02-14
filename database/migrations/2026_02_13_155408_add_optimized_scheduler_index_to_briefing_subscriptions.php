<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the existing (next_scheduled_at, is_active) index with
     * (is_active, next_scheduled_at) for better selectivity in the
     * scheduler query which filters is_active = true first.
     */
    public function up(): void
    {
        Schema::table('briefing_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('briefing_subscriptions_next_scheduled_at_is_active_index');
            $table->index(['is_active', 'next_scheduled_at'], 'briefing_subscriptions_is_active_next_scheduled_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('briefing_subscriptions_is_active_next_scheduled_at_index');
            $table->index(['next_scheduled_at', 'is_active'], 'briefing_subscriptions_next_scheduled_at_is_active_index');
        });
    }
};
