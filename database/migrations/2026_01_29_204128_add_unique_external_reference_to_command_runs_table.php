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
            $table->unique(['workspace_id', 'external_reference'], 'command_runs_workspace_external_reference_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('command_runs', function (Blueprint $table): void {
            $table->dropUnique('command_runs_workspace_external_reference_unique');
        });
    }
};
