<?php

declare(strict_types=1);

use App\Services\Plans\ValueObjects\PlanLimitResult;

it('can be constructed with all parameters', function (): void {
    $result = new PlanLimitResult(
        allowed: false,
        message: 'Limit exceeded',
        code: 'LIMIT_EXCEEDED',
    );

    expect($result->allowed)->toBeFalse();
    expect($result->message)->toBe('Limit exceeded');
    expect($result->code)->toBe('LIMIT_EXCEEDED');
});

it('can be constructed with allowed only', function (): void {
    $result = new PlanLimitResult(allowed: true);

    expect($result->allowed)->toBeTrue();
    expect($result->message)->toBeNull();
    expect($result->code)->toBeNull();
});

it('creates allowed result', function (): void {
    $result = PlanLimitResult::allow();

    expect($result->allowed)->toBeTrue();
    expect($result->message)->toBeNull();
    expect($result->code)->toBeNull();
});

it('creates denied result with message and code', function (): void {
    $result = PlanLimitResult::deny(
        message: 'Monthly review limit reached',
        code: 'REVIEW_LIMIT',
    );

    expect($result->allowed)->toBeFalse();
    expect($result->message)->toBe('Monthly review limit reached');
    expect($result->code)->toBe('REVIEW_LIMIT');
});

it('checks if result is allowed when true', function (): void {
    $result = PlanLimitResult::allow();

    expect($result->isAllowed())->toBeTrue();
    expect($result->isDenied())->toBeFalse();
});

it('checks if result is denied when false', function (): void {
    $result = PlanLimitResult::deny('Denied', 'CODE');

    expect($result->isAllowed())->toBeFalse();
    expect($result->isDenied())->toBeTrue();
});

it('gets the denial message', function (): void {
    $result = PlanLimitResult::deny('Custom message', 'CUSTOM');

    expect($result->getMessage())->toBe('Custom message');
});

it('returns null message for allowed result', function (): void {
    $result = PlanLimitResult::allow();

    expect($result->getMessage())->toBeNull();
});

it('implements EnforcementResult interface', function (): void {
    $result = PlanLimitResult::allow();

    expect($result)->toBeInstanceOf(App\Services\Contracts\EnforcementResult::class);
});
