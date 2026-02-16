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
        Schema::table('runs', function (Blueprint $table): void {
            $table->index(['created_at', 'status'], 'runs_created_at_status_index');
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->index(['created_at', 'severity'], 'findings_created_at_severity_index');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->index(['plan_id', 'subscription_status'], 'workspaces_plan_subscription_status_index');
            $table->index(['trial_ends_at'], 'workspaces_trial_ends_at_index');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(['status', 'current_period_end'], 'subscriptions_status_period_end_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_status_period_end_index');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropIndex('workspaces_plan_subscription_status_index');
            $table->dropIndex('workspaces_trial_ends_at_index');
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->dropIndex('findings_created_at_severity_index');
        });

        Schema::table('runs', function (Blueprint $table): void {
            $table->dropIndex('runs_created_at_status_index');
        });
    }
};
