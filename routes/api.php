<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AiOptionController;
use App\Http\Controllers\Api\Analytics\DeveloperLeaderboardController;
use App\Http\Controllers\Api\Analytics\FindingsDistributionController;
use App\Http\Controllers\Api\Analytics\OverviewMetricsController;
use App\Http\Controllers\Api\Analytics\QualityScoreTrendController;
use App\Http\Controllers\Api\Analytics\RepositoryActivityController;
use App\Http\Controllers\Api\Analytics\ResolutionRateController;
use App\Http\Controllers\Api\Analytics\ReviewDurationTrendsController;
use App\Http\Controllers\Api\Analytics\ReviewVelocityController;
use App\Http\Controllers\Api\Analytics\RunActivityTimelineController;
use App\Http\Controllers\Api\Analytics\SuccessRateController;
use App\Http\Controllers\Api\Analytics\TokenUsageController;
use App\Http\Controllers\Api\Analytics\TopCategoriesController;
use App\Http\Controllers\Api\MarkGettingStartedSeenController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Briefings\BriefingController;
use App\Http\Controllers\Briefings\BriefingDownloadController;
use App\Http\Controllers\Briefings\BriefingGenerationController;
use App\Http\Controllers\Briefings\BriefingShareController;
use App\Http\Controllers\Briefings\BriefingSubscriptionController;
use App\Http\Controllers\Briefings\PublicBriefingController;
use App\Http\Controllers\GitHub\ConnectionController;
use App\Http\Controllers\GitHub\RepositoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ListWorkspaceRunsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProviderKeyController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\Subscriptions\ChangeSubscriptionController;
use App\Http\Controllers\Subscriptions\ShowSubscriptionController;
use App\Http\Controllers\Subscriptions\SubscriptionPortalController;
use App\Http\Controllers\Subscriptions\SubscriptionUsageController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\Webhooks\GitHubWebhookController;
use App\Http\Controllers\Webhooks\PolarWebhookController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for the Sentinel API. All routes require Sanctum authentication
| except the invitation acceptance endpoint and webhooks which allow
| unauthenticated requests.
|
*/

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->name('invitations.accept');

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Webhook endpoints for external services. These routes are public and
| authenticate via signature verification instead of tokens.
|
*/

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])->name('webhooks.github');

Route::post('/webhooks/polar', [PolarWebhookController::class, 'handle'])->name('webhooks.polar');

Route::get('/github/callback', [ConnectionController::class, 'callback'])->name('github.callback');

/*
|--------------------------------------------------------------------------
| Public Briefing Share Routes
|--------------------------------------------------------------------------
|
| Public endpoints for viewing shared briefings. These routes are public
| but require a valid share token.
|
*/

Route::get('/briefings/share/{token}', [PublicBriefingController::class, 'show'])->name('briefings.share.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [OAuthController::class, 'user'])->name('user');
    Route::post('/user/mark-getting-started-seen', MarkGettingStartedSeenController::class)->name('user.mark-getting-started-seen');
    Route::post('/logout', [OAuthController::class, 'logout'])->name('logout');

    // Broadcasting auth - must be in API routes to use Sanctum properly
    Route::post('/broadcasting/auth', [Illuminate\Broadcasting\BroadcastController::class, 'authenticate'])->name('broadcasting.auth');

    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/ai-options/{provider}', AiOptionController::class)->name('ai-options.index');

    // Notifications
    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/{notification}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.mark-unread');
    });

    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::prefix('workspaces/{workspace}')->middleware('workspace.access')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::post('/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::patch('/', [WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::delete('/', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        Route::get('/members', [TeamMemberController::class, 'index'])->name('members.index');
        Route::patch('/members/{member}', [TeamMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [TeamMemberController::class, 'destroy'])->name('members.destroy');

        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        // GitHub Integration
        Route::get('/github/connection', [ConnectionController::class, 'show'])->name('github.connection.show');
        Route::post('/github/connect', [ConnectionController::class, 'store'])->name('github.connection.store');
        Route::delete('/github/disconnect', [ConnectionController::class, 'destroy'])->name('github.connection.destroy');

        Route::get('/repositories', [RepositoryController::class, 'index'])->name('repositories.index');
        Route::post('/repositories/sync', [RepositoryController::class, 'sync'])->name('repositories.sync');
        Route::get('/repositories/{repository}', [RepositoryController::class, 'show'])->name('repositories.show');
        Route::patch('/repositories/{repository}', [RepositoryController::class, 'update'])->name('repositories.update');
        Route::get('/repositories/{repository}/runs', [RunController::class, 'index'])->name('runs.index');

        // Provider Keys (BYOK)
        Route::get('/repositories/{repository}/provider-keys', [ProviderKeyController::class, 'index'])->name('provider-keys.index');
        Route::post('/repositories/{repository}/provider-keys', [ProviderKeyController::class, 'store'])->name('provider-keys.store');
        Route::patch('/repositories/{repository}/provider-keys/{providerKey}', [ProviderKeyController::class, 'update'])->name('provider-keys.update');
        Route::delete('/repositories/{repository}/provider-keys/{providerKey}', [ProviderKeyController::class, 'destroy'])->name('provider-keys.destroy');

        // Workspace-level runs
        Route::get('/runs', ListWorkspaceRunsController::class)->name('runs.workspace');
        Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');

        // Subscription & Usage
        Route::get('/subscription', ShowSubscriptionController::class)->name('subscriptions.show');
        Route::post('/subscription/change', ChangeSubscriptionController::class)->name('subscriptions.change');
        Route::post('/subscription/portal', SubscriptionPortalController::class)->name('subscriptions.portal');
        Route::get('/usage', SubscriptionUsageController::class)->name('usage.show');

        // Activities
        Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');

        // Analytics
        Route::prefix('analytics')->group(function (): void {
            Route::get('/overview', OverviewMetricsController::class)->name('analytics.overview');
            Route::get('/timeline', RunActivityTimelineController::class)->name('analytics.timeline');
            Route::get('/findings-distribution', FindingsDistributionController::class)->name('analytics.findings-distribution');
            Route::get('/top-categories', TopCategoriesController::class)->name('analytics.top-categories');
            Route::get('/repository-activity', RepositoryActivityController::class)->name('analytics.repository-activity');
            Route::get('/developer-leaderboard', DeveloperLeaderboardController::class)->name('analytics.developer-leaderboard');
            Route::get('/duration-trends', ReviewDurationTrendsController::class)->name('analytics.duration-trends');
            Route::get('/token-usage', TokenUsageController::class)->name('analytics.token-usage');
            Route::get('/success-rate', SuccessRateController::class)->name('analytics.success-rate');
            Route::get('/quality-score', QualityScoreTrendController::class)->name('analytics.quality-score');
            Route::get('/resolution-rate', ResolutionRateController::class)->name('analytics.resolution-rate');
            Route::get('/velocity', ReviewVelocityController::class)->name('analytics.velocity');
        });

        // Briefings
        Route::prefix('briefings')->group(function (): void {
            Route::get('/', [BriefingController::class, 'index'])->name('briefings.index');
            Route::get('/{slug}', [BriefingController::class, 'show'])->name('briefings.show');
            Route::post('/{slug}/generate', [BriefingGenerationController::class, 'store'])->name('briefings.generate');
        });

        // Briefing Generations
        Route::prefix('briefing-generations')->group(function (): void {
            Route::get('/', [BriefingGenerationController::class, 'index'])->name('briefing-generations.index');
            Route::get('/{generation}', [BriefingGenerationController::class, 'show'])->name('briefing-generations.show');
            Route::get('/{generation}/download/{format}', BriefingDownloadController::class)->name('briefing-generations.download');
            Route::post('/{generation}/share', [BriefingShareController::class, 'store'])->name('briefing-generations.share');
        });

        // Briefing Subscriptions
        Route::prefix('briefing-subscriptions')->group(function (): void {
            Route::get('/', [BriefingSubscriptionController::class, 'index'])->name('briefing-subscriptions.index');
            Route::post('/', [BriefingSubscriptionController::class, 'store'])->name('briefing-subscriptions.store');
            Route::patch('/{subscription}', [BriefingSubscriptionController::class, 'update'])->name('briefing-subscriptions.update');
            Route::delete('/{subscription}', [BriefingSubscriptionController::class, 'destroy'])->name('briefing-subscriptions.destroy');
        });

        // Briefing Shares
        Route::delete('/briefing-shares/{share}', [BriefingShareController::class, 'destroy'])->name('briefing-shares.destroy');
    });

});
