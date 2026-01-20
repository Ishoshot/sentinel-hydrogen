<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only create vector extension on PostgreSQL
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('CREATE EXTENSION IF NOT EXISTS vector');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('DROP EXTENSION IF EXISTS vector');
        }
    }
};
