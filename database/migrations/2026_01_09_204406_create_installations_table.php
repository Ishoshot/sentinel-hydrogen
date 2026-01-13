<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained()->noActionOnDelete();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->unsignedBigInteger('installation_id')->unique();
            $table->string('account_type');
            $table->string('account_login');
            $table->string('account_avatar_url')->nullable();
            $table->string('status');
            $table->json('permissions')->nullable();
            $table->json('events')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id']);
            $table->index(['connection_id']);
            $table->index(['status']);
            $table->index(['account_login']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installations');
    }
};
