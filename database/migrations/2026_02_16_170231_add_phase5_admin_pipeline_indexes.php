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
        Schema::table('command_runs', function (Blueprint $table): void {
            $table->index(['created_at', 'status'], 'command_runs_created_at_status_index');
            $table->index(['workspace_id', 'status', 'created_at'], 'command_runs_workspace_status_created_at_index');
        });

        Schema::table('briefing_generations', function (Blueprint $table): void {
            $table->index(['created_at', 'status'], 'briefing_generations_created_at_status_index');
            $table->index(['workspace_id', 'status', 'created_at'], 'briefing_generations_workspace_status_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_generations', function (Blueprint $table): void {
            $table->dropIndex('briefing_generations_created_at_status_index');
            $table->dropIndex('briefing_generations_workspace_status_created_at_index');
        });

        Schema::table('command_runs', function (Blueprint $table): void {
            $table->dropIndex('command_runs_created_at_status_index');
            $table->dropIndex('command_runs_workspace_status_created_at_index');
        });
    }
};
