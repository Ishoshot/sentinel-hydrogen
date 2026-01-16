<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AiOptionController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\PromotionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| These routes are for the admin dashboard API. All routes are prefixed
| with /admin and use the admin guard for authentication.
|
*/

Route::prefix('auth')->group(function (): void {
    Route::post('/login', LoginController::class)->name('admin.login');
});

Route::middleware('admin.auth')->group(function (): void {
    Route::apiResource('promotions', PromotionController::class);
    Route::apiResource('ai-options', AiOptionController::class)
        ->parameters(['ai-options' => 'ai_option'])
        ->names([
            'index' => 'admin.ai-options.index',
            'store' => 'admin.ai-options.store',
            'show' => 'admin.ai-options.show',
            'update' => 'admin.ai-options.update',
            'destroy' => 'admin.ai-options.destroy',
        ]);
});
