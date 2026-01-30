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
        Schema::table('provider_models', function (Blueprint $table): void {
            $table->unsignedInteger('context_window_tokens')->nullable()->after('sort_order');
            $table->unsignedInteger('max_output_tokens')->nullable()->after('context_window_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_models', function (Blueprint $table): void {
            $table->dropColumn(['context_window_tokens', 'max_output_tokens']);
        });
    }
};
