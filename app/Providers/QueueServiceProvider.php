<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Queue\QueueResolver;
use App\Services\Queue\Rules\AnnotationJobRule;
use App\Services\Queue\Rules\LongRunningJobRule;
use App\Services\Queue\Rules\ReviewJobTierRule;
use App\Services\Queue\Rules\SystemJobRule;
use App\Services\Queue\Rules\WebhookJobRule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the queue routing system.
 *
 * Registers the QueueResolver and all queue rules.
 */
final class QueueServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register individual rules
        $this->app->bind(SystemJobRule::class);
        $this->app->bind(WebhookJobRule::class);
        $this->app->bind(ReviewJobTierRule::class);
        $this->app->bind(AnnotationJobRule::class);
        $this->app->bind(LongRunningJobRule::class);

        // Register the QueueResolver as a singleton with all rules
        $this->app->singleton(QueueResolver::class, function (Application $app): QueueResolver {
            /** @var array<int, \App\Services\Queue\Contracts\QueueRule> $rules */
            $rules = [
                $app->make(SystemJobRule::class),
                $app->make(WebhookJobRule::class),
                $app->make(ReviewJobTierRule::class),
                $app->make(AnnotationJobRule::class),
                $app->make(LongRunningJobRule::class),
            ];

            $resolver = new QueueResolver($rules);

            // Enable debug mode in non-production environments
            if ($app->environment('local', 'testing', 'development')) {
                $resolver->enableDebugMode();
            }

            return $resolver;
        });

        // Bind the interface to the QueueResolver for dependency injection
        $this->app->alias(QueueResolver::class, 'queue.resolver');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, class-string|string>
     */
    public function provides(): array
    {
        return [
            QueueResolver::class,
            'queue.resolver',
            SystemJobRule::class,
            WebhookJobRule::class,
            ReviewJobTierRule::class,
            AnnotationJobRule::class,
            LongRunningJobRule::class,
        ];
    }
}
