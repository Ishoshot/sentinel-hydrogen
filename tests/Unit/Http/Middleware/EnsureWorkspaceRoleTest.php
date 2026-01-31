<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureWorkspaceRole;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('allows users with matching role', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()
        ->forUser($user)
        ->forWorkspace($workspace)
        ->admin()
        ->create();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route('GET', '/test', []);
    $route->bind($request);
    $route->setParameter('workspace', $workspace);
    $request->setRouteResolver(fn () => $route);

    $middleware = new EnsureWorkspaceRole;

    $response = $middleware->handle($request, fn () => response('OK'), 'admin');

    expect($response->getContent())->toBe('OK');
});

it('allows owner when admin or owner is required', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()
        ->forUser($user)
        ->forWorkspace($workspace)
        ->owner()
        ->create();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route('GET', '/test', []);
    $route->bind($request);
    $route->setParameter('workspace', $workspace);
    $request->setRouteResolver(fn () => $route);

    $middleware = new EnsureWorkspaceRole;

    $response = $middleware->handle($request, fn () => response('OK'), 'admin', 'owner');

    expect($response->getContent())->toBe('OK');
});

it('aborts 404 when workspace not in route', function (): void {
    $request = Request::create('/test', 'GET');

    $route = new Route('GET', '/test', []);
    $route->bind($request);
    $route->setParameter('workspace', null);
    $request->setRouteResolver(fn () => $route);

    $middleware = new EnsureWorkspaceRole;

    $middleware->handle($request, fn () => response('OK'), 'admin');
})->throws(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

it('aborts 403 when user is not authenticated', function (): void {
    $workspace = Workspace::factory()->create();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => null);

    $route = new Route('GET', '/test', []);
    $route->bind($request);
    $route->setParameter('workspace', $workspace);
    $request->setRouteResolver(fn () => $route);

    $middleware = new EnsureWorkspaceRole;

    $middleware->handle($request, fn () => response('OK'), 'admin');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('aborts 403 when user is not a member of workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route('GET', '/test', []);
    $route->bind($request);
    $route->setParameter('workspace', $workspace);
    $request->setRouteResolver(fn () => $route);

    $middleware = new EnsureWorkspaceRole;

    $middleware->handle($request, fn () => response('OK'), 'admin');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('aborts 403 when user role does not match', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()
        ->forUser($user)
        ->forWorkspace($workspace)
        ->member()
        ->create();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route('GET', '/test', []);
    $route->bind($request);
    $route->setParameter('workspace', $workspace);
    $request->setRouteResolver(fn () => $route);

    $middleware = new EnsureWorkspaceRole;

    $middleware->handle($request, fn () => response('OK'), 'admin');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);
