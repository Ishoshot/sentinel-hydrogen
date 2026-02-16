<?php

declare(strict_types=1);

use App\Http\Requests\ProviderKey\UpdateProviderKeyRequest;

it('authorizes all requests', function (): void {
    $request = new UpdateProviderKeyRequest();

    expect($request->authorize())->toBeTrue();
});

it('allows nullable provider_model_id', function (): void {
    $request = UpdateProviderKeyRequest::create('/provider-keys/1', 'PUT', []);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects non-integer provider_model_id', function (): void {
    $request = UpdateProviderKeyRequest::create('/provider-keys/1', 'PUT', [
        'provider_model_id' => 'not-an-integer',
    ]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('provider_model_id'))->toBeTrue();
});

it('rejects provider_model_id that does not exist in database', function (): void {
    $request = UpdateProviderKeyRequest::create('/provider-keys/1', 'PUT', [
        'provider_model_id' => 999999,
    ]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('provider_model_id'))->toBeTrue();
});

it('returns null from providerModelId when not provided', function (): void {
    $request = UpdateProviderKeyRequest::create('/provider-keys/1', 'PUT', []);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->providerModelId())->toBeNull();
});

it('returns integer from providerModelId when provided with valid id', function (): void {
    // Insert a provider_models record directly so the exists rule passes
    $id = Illuminate\Support\Facades\DB::table('provider_models')->insertGetId([
        'provider' => 'anthropic',
        'identifier' => 'claude-3-opus',
        'name' => 'Claude 3 Opus',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $request = UpdateProviderKeyRequest::create('/provider-keys/1', 'PUT', [
        'provider_model_id' => $id,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->providerModelId())->toBe((int) $id);
});

it('accepts explicit null for provider_model_id', function (): void {
    $request = UpdateProviderKeyRequest::create('/provider-keys/1', 'PUT', [
        'provider_model_id' => null,
    ]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});
