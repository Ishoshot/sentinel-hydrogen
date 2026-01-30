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
        Schema::create('incoming_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('partner');
            $table->string('webhook_id')->nullable();
            $table->string('event_type')->nullable();
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->string('ip_address')->nullable();
            $table->integer('response_code')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['partner', 'webhook_id']);
            $table->index(['partner', 'event_type']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_webhooks');
    }
};
