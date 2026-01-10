<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Reviews\Contracts\PullRequestDataResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\DefaultReviewEngine;
use App\Services\Reviews\GitHubPullRequestDataResolver;
use App\Services\Reviews\PrismReviewEngine;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PullRequestDataResolver::class, GitHubPullRequestDataResolver::class);

        $this->app->bind(ReviewEngine::class, function (): ReviewEngine {
            $anthropicKey = config('prism.providers.anthropic.api_key', '');
            $openAiKey = config('prism.providers.openai.api_key', '');

            if ($anthropicKey !== '' || $openAiKey !== '') {
                return app(PrismReviewEngine::class);
            }

            return app(DefaultReviewEngine::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
