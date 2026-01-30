<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CodeIndexing\CodeIndexingService;
use App\Services\CodeIndexing\CodeSearchService;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use App\Services\CodeIndexing\EmbeddingService;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for code indexing services.
 *
 * Registers services for indexing repository code, generating
 * embeddings, and performing semantic code search.
 */
final class CodeIndexingServiceProvider extends ServiceProvider
{
    /**
     * Register code indexing services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(CodeIndexingServiceContract::class, CodeIndexingService::class);
        $this->app->bind(CodeSearchServiceContract::class, CodeSearchService::class);
        $this->app->bind(EmbeddingServiceContract::class, EmbeddingService::class);
    }
}
