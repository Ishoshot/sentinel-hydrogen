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
            $table->foreignId('initiated_by_id')->nullable()->after('repository_id')->constrained('users');
            $table->integer('pr_number')->nullable()->after('external_reference');
            $table->string('pr_title')->nullable()->after('pr_number');
            $table->string('base_branch')->nullable()->after('pr_title');
            $table->string('head_branch')->nullable()->after('base_branch');
            $table->integer('duration_seconds')->nullable()->after('completed_at');

            $table->index('initiated_by_id');
            $table->index(['workspace_id', 'initiated_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'initiated_by_id']);
            $table->dropIndex(['initiated_by_id']);
            $table->dropForeign(['initiated_by_id']);

            $table->dropColumn([
                'initiated_by_id',
                'pr_number',
                'pr_title',
                'base_branch',
                'head_branch',
                'duration_seconds',
            ]);
        });
    }
};
