<?php

declare(strict_types=1);

use App\Exceptions\NoProviderKeyException;
use App\Exceptions\Rendering\BillingRuntimeExceptionRenderer;
use App\Exceptions\Rendering\InvalidArgumentJsonRenderer;
use App\Exceptions\Rendering\OAuthCallbackRedirectRenderer;
use App\Exceptions\Rendering\WebhookSignatureRenderer;
use App\Exceptions\SentinelConfig\ConfigParseException;
use App\Exceptions\SentinelConfig\ConfigValidationException;
use App\Models\Workspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/admin')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'workspace.access' => App\Http\Middleware\EnsureWorkspaceAccess::class,
            'workspace.role' => App\Http\Middleware\EnsureWorkspaceRole::class,
            'admin.auth' => App\Http\Middleware\EnsureAdminAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            NoProviderKeyException::class,
            ConfigParseException::class,
            ConfigValidationException::class,
        ]);

        $exceptions->context(function (): array {
            $workspace = request()->route('workspace');

            return [
                'url' => request()->fullUrl(),
                'user_id' => auth()->id(),
                'workspace_id' => $workspace instanceof Workspace ? $workspace->id : null,
            ];
        });

        $renderers = [
            new InvalidArgumentJsonRenderer,
            new OAuthCallbackRedirectRenderer,
            new WebhookSignatureRenderer,
            new BillingRuntimeExceptionRenderer,
        ];

        $exceptions->render(function (Throwable $e, Request $request) use ($renderers) {
            foreach ($renderers as $renderer) {
                $response = $renderer->render($e, $request);

                if ($response !== null) {
                    return $response;
                }
            }

            return null;
        });
    })->create();
