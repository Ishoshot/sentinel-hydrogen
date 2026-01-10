<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\GitHub\ConnectionController;
use App\Http\Controllers\GitHub\RepositoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\Webhooks\GitHubWebhookController;
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

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->name('webhooks.github');

Route::get('/github/callback', [ConnectionController::class, 'callback'])
    ->name('github.callback');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [OAuthController::class, 'user'])->name('user');
    Route::post('/logout', [OAuthController::class, 'logout'])->name('logout');

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

    Route::prefix('workspaces/{workspace}')
        ->middleware('workspace.access')
        ->group(function (): void {
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
            Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');

            // Activities
            Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
        });

});
