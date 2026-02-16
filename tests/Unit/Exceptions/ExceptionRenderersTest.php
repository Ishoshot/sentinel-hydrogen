<?php

declare(strict_types=1);

use App\Exceptions\Rendering\BillingRuntimeExceptionRenderer;
use App\Exceptions\Rendering\InvalidArgumentJsonRenderer;
use App\Exceptions\Rendering\OAuthCallbackRedirectRenderer;
use App\Exceptions\Rendering\WebhookSignatureRenderer;
use Illuminate\Http\Request;
use StandardWebhooks\Exception\WebhookVerificationException;

// --- BillingRuntimeExceptionRenderer ---

it('billing renderer returns 400 json for RuntimeException on billing routes', function (): void {
    $renderer = new BillingRuntimeExceptionRenderer;
    $exception = new RuntimeException('Subscription already cancelled');

    $request = Request::create('/api/v1/subscriptions/cancel', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(400)
        ->and($response->getData(true))->toBe(['message' => 'Subscription already cancelled']);
});

it('billing renderer returns 400 for billing routes', function (): void {
    $renderer = new BillingRuntimeExceptionRenderer;
    $exception = new RuntimeException('Payment failed');

    $request = Request::create('/api/v1/billing/checkout', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(400);
});

it('billing renderer returns null for non-RuntimeException', function (): void {
    $renderer = new BillingRuntimeExceptionRenderer;
    $exception = new InvalidArgumentException('Not a runtime exception');

    $request = Request::create('/api/v1/subscriptions/cancel', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('billing renderer returns null for non-billing routes', function (): void {
    $renderer = new BillingRuntimeExceptionRenderer;
    $exception = new RuntimeException('Something broke');

    $request = Request::create('/api/v1/workspaces', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('billing renderer returns null when request does not expect json', function (): void {
    $renderer = new BillingRuntimeExceptionRenderer;
    $exception = new RuntimeException('Payment failed');

    $request = Request::create('/api/v1/subscriptions/cancel', 'POST');
    $request->headers->set('Accept', 'text/html');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

// --- OAuthCallbackRedirectRenderer ---

it('oauth renderer redirects to frontend error page for callback routes', function (): void {
    config()->set('app.frontend_url', 'https://app.sentinel.test');

    $renderer = new OAuthCallbackRedirectRenderer;
    $exception = new RuntimeException('OAuth failed');

    $request = Request::create('/auth/github/callback', 'GET');
    $request->setRouteResolver(function () {
        $route = new Illuminate\Routing\Route('GET', 'auth/{provider}/callback', []);
        $route->bind(Request::create('/auth/github/callback'));

        return $route;
    });

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getTargetUrl())->toContain('https://app.sentinel.test/auth/error')
        ->and($response->getTargetUrl())->toContain('message=')
        ->and($response->getTargetUrl())->toContain('Github');
});

it('oauth renderer returns null for non-callback routes', function (): void {
    $renderer = new OAuthCallbackRedirectRenderer;
    $exception = new RuntimeException('OAuth failed');

    $request = Request::create('/api/v1/workspaces', 'GET');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('oauth renderer uses generic message when provider is not a string', function (): void {
    config()->set('app.frontend_url', 'https://app.sentinel.test');

    $renderer = new OAuthCallbackRedirectRenderer;
    $exception = new RuntimeException('OAuth failed');

    $request = Request::create('/auth/github/callback', 'GET');
    $request->setRouteResolver(function () {
        $route = new Illuminate\Routing\Route('GET', 'auth/{provider}/callback', []);
        $route->bind(Request::create('/auth/github/callback'));
        // Override provider parameter to null
        $route->setParameter('provider', null);

        return $route;
    });

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull();
    $decodedUrl = urldecode($response->getTargetUrl());
    expect($decodedUrl)->toContain('Authentication failed. Please try again.');
});

// --- WebhookSignatureRenderer ---

it('webhook renderer returns 400 for WebhookVerificationException on webhook routes', function (): void {
    $renderer = new WebhookSignatureRenderer;
    $exception = new WebhookVerificationException('Signature mismatch');

    $request = Request::create('/webhooks/polar', 'POST');

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(400)
        ->and($response->getData(true))->toBe(['error' => 'Invalid webhook signature.']);
});

it('webhook renderer returns 400 for polar RuntimeException with webhook signature message', function (): void {
    $renderer = new WebhookSignatureRenderer;
    $exception = new RuntimeException('Invalid webhook signature payload');

    $request = Request::create('/webhooks/polar', 'POST');

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(400)
        ->and($response->getData(true))->toBe(['error' => 'Invalid webhook payload.']);
});

it('webhook renderer returns null for non-webhook routes', function (): void {
    $renderer = new WebhookSignatureRenderer;
    $exception = new WebhookVerificationException('Signature mismatch');

    $request = Request::create('/api/v1/workspaces', 'GET');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('webhook renderer returns null for unrelated exceptions on webhook routes', function (): void {
    $renderer = new WebhookSignatureRenderer;
    $exception = new InvalidArgumentException('Some other error');

    $request = Request::create('/webhooks/github', 'POST');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('webhook renderer returns null for non-polar RuntimeException without signature message', function (): void {
    $renderer = new WebhookSignatureRenderer;
    $exception = new RuntimeException('Something else went wrong');

    $request = Request::create('/webhooks/polar', 'POST');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('webhook renderer returns null for RuntimeException with signature message on non-polar webhook', function (): void {
    $renderer = new WebhookSignatureRenderer;
    $exception = new RuntimeException('Invalid webhook signature');

    $request = Request::create('/webhooks/github', 'POST');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

// --- InvalidArgumentJsonRenderer ---

it('invalid argument renderer returns 422 json for InvalidArgumentException', function (): void {
    $renderer = new InvalidArgumentJsonRenderer;
    $exception = new InvalidArgumentException('The field is invalid');

    $request = Request::create('/api/v1/workspaces', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = $renderer->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe(['message' => 'The field is invalid']);
});

it('invalid argument renderer returns null for non-InvalidArgumentException', function (): void {
    $renderer = new InvalidArgumentJsonRenderer;
    $exception = new RuntimeException('Something else');

    $request = Request::create('/api/v1/workspaces', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});

it('invalid argument renderer returns null when request does not expect json', function (): void {
    $renderer = new InvalidArgumentJsonRenderer;
    $exception = new InvalidArgumentException('Bad input');

    $request = Request::create('/api/v1/workspaces', 'POST');
    $request->headers->set('Accept', 'text/html');

    $response = $renderer->render($exception, $request);

    expect($response)->toBeNull();
});
