<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for the Sentinel API. All routes require Sanctum authentication
| except the invitation acceptance endpoint which allows unauthenticated
| requests to check invitation status.
|
*/

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->name('invitations.accept');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [OAuthController::class, 'user'])->name('user');
    Route::post('/logout', [OAuthController::class, 'logout'])->name('logout');

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
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        });
});
