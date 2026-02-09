<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provider_keys', function (Blueprint $table): void {
            // Drop the existing unique constraint on (repository_id, provider)
            $table->dropUnique(['repository_id', 'provider']);

            // Drop the existing FK on repository_id
            $table->dropForeign(['repository_id']);
        });

        Schema::table('provider_keys', function (Blueprint $table): void {
            // Make repository_id nullable and re-add FK
            $table->unsignedBigInteger('repository_id')->nullable()->change();
            $table->foreign('repository_id')->references('id')->on('repositories')->noActionOnDelete();

            // Add composite unique for repo-level keys
            $table->unique(['workspace_id', 'repository_id', 'provider']);
        });

        // Add partial unique index for workspace-level keys (repository_id IS NULL)
        DB::statement(
            'CREATE UNIQUE INDEX provider_keys_workspace_provider_unique ON provider_keys (workspace_id, provider) WHERE repository_id IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS provider_keys_workspace_provider_unique');

        Schema::table('provider_keys', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'repository_id', 'provider']);
            $table->dropForeign(['repository_id']);
        });

        Schema::table('provider_keys', function (Blueprint $table): void {
            $table->unsignedBigInteger('repository_id')->nullable(false)->change();
            $table->foreign('repository_id')->references('id')->on('repositories')->noActionOnDelete();
            $table->unique(['repository_id', 'provider']);
        });
    }
};
