<?php

declare(strict_types=1);

use App\Http\Requests\GitHub\ConnectionCallbackRequest;

it('authorizes all requests', function (): void {
    $request = new ConnectionCallbackRequest();

    expect($request->authorize())->toBeTrue();
});

it('requires installation_id', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', []);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('installation_id'))->toBeTrue();
});

it('requires installation_id to be an integer', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', ['installation_id' => 'abc']);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('installation_id'))->toBeTrue();
});

it('accepts valid installation_id', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', ['installation_id' => 12345]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('allows nullable state', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
    ]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('allows string state', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
        'state' => 'some-state-value',
    ]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('allows nullable setup_action', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
    ]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('returns installation id as integer from installationId method', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 99999,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->installationId())->toBe(99999);
});

it('returns state string from state method', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
        'state' => 'my-state-token',
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->state())->toBe('my-state-token');
});

it('returns null from state method when state is not provided', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->state())->toBeNull();
});

it('returns setup action from setupAction method', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
        'setup_action' => 'install',
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->setupAction())->toBe('install');
});

it('returns null from setupAction method when not provided', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->setupAction())->toBeNull();
});

it('returns true from wasCancelled when setup_action is request', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
        'setup_action' => 'request',
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->wasCancelled())->toBeTrue();
});

it('returns false from wasCancelled when setup_action is install', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
        'setup_action' => 'install',
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->wasCancelled())->toBeFalse();
});

it('returns false from wasCancelled when setup_action is not provided', function (): void {
    $request = ConnectionCallbackRequest::create('/callback', 'GET', [
        'installation_id' => 12345,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->wasCancelled())->toBeFalse();
});
