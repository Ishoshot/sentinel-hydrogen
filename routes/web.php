<?php

declare(strict_types=1);

use App\Enums\Auth\OAuthProvider;
use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| OAuth routes require browser redirects and cannot be API routes.
| All other routes are in routes/api.php for the frontend API.
|
*/

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])
    ->name('auth.redirect')
    ->whereIn('provider', OAuthProvider::values());

Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])
    ->name('auth.callback')
    ->whereIn('provider', OAuthProvider::values());

Route::get('/', function () {
    return response()->json([
        'version' => '1.0.0',
        'name' => 'Sentinel',
        'description' => 'Sentinel is an AI-powered code review and code intelligence platform that helps engineering teams maintain high code quality, reduce risk, and ship with confidence.',
        'links' => [
            'app' => 'https://usesentinel.ai',
            'docs' => 'https://docs.usesentinel.ai',
        ],
        'author' => 'Oluwatobi Ishola',
    ]);
});
