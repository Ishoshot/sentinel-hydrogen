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
            $table->index(['workspace_id', 'repository_id', 'created_at'], 'runs_ws_repo_created_idx');
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->index(['workspace_id', 'run_id', 'created_at'], 'findings_ws_run_created_idx');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('runs', function (Blueprint $table): void {
            $table->fullText(['pr_title', 'base_branch', 'head_branch'], 'runs_issue_search_fulltext');
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->fullText(['title', 'description', 'file_path'], 'findings_issue_search_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            Schema::table('findings', function (Blueprint $table): void {
                $table->dropFullText('findings_issue_search_fulltext');
            });

            Schema::table('runs', function (Blueprint $table): void {
                $table->dropFullText('runs_issue_search_fulltext');
            });
        }

        Schema::table('findings', function (Blueprint $table): void {
            $table->dropIndex('findings_ws_run_created_idx');
        });

        Schema::table('runs', function (Blueprint $table): void {
            $table->dropIndex('runs_ws_repo_created_idx');
        });
    }
};
