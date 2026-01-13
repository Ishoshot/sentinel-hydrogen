<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installation_id')->constrained()->noActionOnDelete();
            $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
            $table->unsignedBigInteger('github_id');
            $table->string('name');
            $table->string('full_name');
            $table->boolean('private')->default(false);
            $table->string('default_branch')->default('main');
            $table->string('language')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['installation_id', 'github_id']);
            $table->index(['workspace_id']);
            $table->index(['installation_id']);
            $table->index(['full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
