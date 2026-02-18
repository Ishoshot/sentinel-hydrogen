<?php

declare(strict_types=1);

use App\Enums\CodeIndexing\CodeIndexScopeType;
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
        Schema::table('code_indexes', function (Blueprint $table): void {
            $table->string('scope_type', 20)
                ->default(CodeIndexScopeType::Baseline->value)
                ->after('repository_id');
            $table->string('scope_ref', 120)
                ->default(CodeIndexScopeType::Baseline->value)
                ->after('scope_type');
            $table->unsignedInteger('pull_request_number')
                ->nullable()
                ->after('scope_ref');
            $table->string('head_sha')
                ->nullable()
                ->after('pull_request_number');
        });

        Schema::table('code_indexes', function (Blueprint $table): void {
            $table->dropUnique('code_indexes_repository_id_commit_sha_file_path_unique');

            $table->unique(
                ['repository_id', 'scope_type', 'scope_ref', 'file_path'],
                'code_indexes_repository_scope_file_unique'
            );

            $table->index(
                ['repository_id', 'scope_type', 'scope_ref'],
                'code_indexes_repository_scope_index'
            );

            $table->index(
                ['repository_id', 'pull_request_number', 'head_sha'],
                'code_indexes_repository_pr_head_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code_indexes', function (Blueprint $table): void {
            $table->dropIndex('code_indexes_repository_pr_head_index');
            $table->dropIndex('code_indexes_repository_scope_index');
            $table->dropUnique('code_indexes_repository_scope_file_unique');

            $table->unique(
                ['repository_id', 'commit_sha', 'file_path'],
                'code_indexes_repository_id_commit_sha_file_path_unique'
            );

            $table->dropColumn([
                'scope_type',
                'scope_ref',
                'pull_request_number',
                'head_sha',
            ]);
        });
    }
};
