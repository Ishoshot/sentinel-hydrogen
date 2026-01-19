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
        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->renameColumn('checkout_session_id', 'checkout_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->renameColumn('checkout_url', 'checkout_session_id');
        });
    }
};
