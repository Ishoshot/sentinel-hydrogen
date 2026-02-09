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
        Schema::create('slack_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token')->nullable();
            $table->string('bot_user_id', 50)->nullable();
            $table->string('slack_team_id', 50)->nullable();
            $table->string('team_name', 100)->nullable();
            $table->text('scope')->nullable();
            $table->string('authed_user_id', 50)->nullable();
            $table->string('channel_id', 50)->nullable();
            $table->string('channel_name', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->timestamp('state_expires_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_integrations');
    }
};
