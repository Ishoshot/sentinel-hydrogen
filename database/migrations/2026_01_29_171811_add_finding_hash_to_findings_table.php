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
        Schema::table('findings', function (Blueprint $table): void {
            $table->string('finding_hash', 64)->nullable()->after('run_id');
            $table->unique(['run_id', 'finding_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->dropUnique(['run_id', 'finding_hash']);
            $table->dropColumn('finding_hash');
        });
    }
};
