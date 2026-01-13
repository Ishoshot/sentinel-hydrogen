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
        Schema::create('usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('findings_count')->default(0);
            $table->unsignedInteger('annotations_count')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
