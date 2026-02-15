<?php

declare(strict_types=1);

use App\Http\Requests\Run\IndexRunsRequest;

it('authorizes all requests', function (): void {
    $request = new IndexRunsRequest();

    expect($request->authorize())->toBeTrue();
});

it('allows valid per_page values', function (int $perPage): void {
    $request = IndexRunsRequest::create('/runs', 'GET', ['per_page' => $perPage]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
})->with([1, 10, 50, 100]);

it('rejects per_page below minimum', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', ['per_page' => 0]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('per_page'))->toBeTrue();
});

it('rejects per_page above maximum', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', ['per_page' => 101]);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('per_page'))->toBeTrue();
});

it('rejects non-integer per_page', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', ['per_page' => 'abc']);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('per_page'))->toBeTrue();
});

it('passes validation when per_page is not provided', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', []);
    $request->setContainer(app());

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('returns validated per_page value from perPage method', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', ['per_page' => 25]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->perPage())->toBe(25);
});

it('returns default of 20 when per_page is not provided', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', []);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->perPage())->toBe(20);
});

it('caps per_page at 100 via perPage method', function (): void {
    $request = IndexRunsRequest::create('/runs', 'GET', ['per_page' => 100]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->perPage())->toBe(100);
});
