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
            $table->index(
                ['workspace_id', 'repository_id', 'pr_number', 'created_at'],
                'runs_workspace_repo_pr_created_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table): void {
            $table->dropIndex('runs_workspace_repo_pr_created_index');
        });
    }
};
