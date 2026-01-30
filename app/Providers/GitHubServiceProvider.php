<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\GitHub\PostAutoReviewDisabledComment;
use App\Actions\GitHub\PostConfigErrorComment;
use App\Actions\GitHub\PostPullRequestGreeting;
use App\Actions\GitHub\PostSkipReasonComment;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\Contracts\GitHubRateLimiterContract;
use App\Services\GitHub\Contracts\GitHubWebhookServiceContract;
use App\Services\GitHub\GitHubApiService;
use App\Services\GitHub\GitHubAppService;
use App\Services\GitHub\GitHubRateLimiter;
use App\Services\GitHub\GitHubWebhookService;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for GitHub integration services.
 *
 * Registers GitHub API services and PR comment action contracts.
 */
final class GitHubServiceProvider extends ServiceProvider
{
    /**
     * Register GitHub services.
     */
    #[Override]
    public function register(): void
    {
        // GitHub API services
        $this->app->bind(GitHubApiServiceContract::class, GitHubApiService::class);
        $this->app->bind(GitHubAppServiceContract::class, GitHubAppService::class);
        $this->app->bind(GitHubRateLimiterContract::class, GitHubRateLimiter::class);
        $this->app->bind(GitHubWebhookServiceContract::class, GitHubWebhookService::class);

        // GitHub PR comment action contracts
        $this->app->bind(PostsGreetingComment::class, PostPullRequestGreeting::class);
        $this->app->bind(PostsConfigErrorComment::class, PostConfigErrorComment::class);
        $this->app->bind(PostsAutoReviewDisabledComment::class, PostAutoReviewDisabledComment::class);
        $this->app->bind(PostsSkipReasonComment::class, PostSkipReasonComment::class);
    }
}
