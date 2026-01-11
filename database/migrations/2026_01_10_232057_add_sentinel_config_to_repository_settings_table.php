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
        Schema::table('repository_settings', function (Blueprint $table): void {
            // Stores the parsed and validated .sentinel/config.yaml content
            $table->json('sentinel_config')->nullable()->after('review_rules');

            // Timestamp of when the config was last synced from the repository
            $table->timestamp('config_synced_at')->nullable()->after('sentinel_config');

            // Stores error message if config parsing/validation failed
            $table->text('config_error')->nullable()->after('config_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repository_settings', function (Blueprint $table): void {
            $table->dropColumn(['sentinel_config', 'config_synced_at', 'config_error']);
        });
    }
};
