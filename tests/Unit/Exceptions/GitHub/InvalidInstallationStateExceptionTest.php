<?php

declare(strict_types=1);

use App\Exceptions\GitHub\InvalidInstallationStateException;
use Illuminate\Http\Request;

it('creates exception with default message', function (): void {
    $exception = new InvalidInstallationStateException;

    expect($exception->getMessage())->toBe('Invalid or expired installation state.');
});

it('creates exception with custom message', function (): void {
    $exception = new InvalidInstallationStateException('Custom error message');

    expect($exception->getMessage())->toBe('Custom error message');
});

it('renders json response for api requests', function (): void {
    $exception = new InvalidInstallationStateException('Test error');
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true))->toBe([
            'message' => 'Test error',
            'error' => 'invalid_state',
        ]);
});

it('renders redirect response for web requests', function (): void {
    $exception = new InvalidInstallationStateException('Test error');
    $request = Request::create('/test', 'GET');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toContain('/workspaces');
});

it('renders redirect to custom url', function (): void {
    $exception = new InvalidInstallationStateException('Test error', '/custom-redirect');
    $request = Request::create('/test', 'GET');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toContain('/custom-redirect');
});
