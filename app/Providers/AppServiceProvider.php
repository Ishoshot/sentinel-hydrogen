<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Reviews\Contracts\PullRequestDataResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\DefaultReviewEngine;
use App\Services\Reviews\GitHubPullRequestDataResolver;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PullRequestDataResolver::class, GitHubPullRequestDataResolver::class);
        $this->app->bind(ReviewEngine::class, DefaultReviewEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
