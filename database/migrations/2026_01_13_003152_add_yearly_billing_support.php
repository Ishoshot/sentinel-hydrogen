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
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('price_yearly')->nullable()->after('price_monthly');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('billing_interval')->default('monthly')->after('plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('price_yearly');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('billing_interval');
        });
    }
};
