<?php

declare(strict_types=1);

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Billing\Policies\PolarProductIdPolicy;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->policy = new PolarProductIdPolicy;
    $this->workspace = (new Workspace)->forceFill(['id' => 1, 'name' => 'Test Workspace']);
    $this->plan = (new Plan)->forceFill(['id' => 1, 'tier' => 'pro']);
});

it('resolves product id for monthly interval', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => ['pro' => 'prod_monthly_pro'],
        'yearly' => ['pro' => 'prod_yearly_pro'],
    ]]);

    $productId = $this->policy->resolve($this->workspace, $this->plan, BillingInterval::Monthly);

    expect($productId)->toBe('prod_monthly_pro');
});

it('resolves product id for yearly interval', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => ['pro' => 'prod_monthly_pro'],
        'yearly' => ['pro' => 'prod_yearly_pro'],
    ]]);

    $productId = $this->policy->resolve($this->workspace, $this->plan, BillingInterval::Yearly);

    expect($productId)->toBe('prod_yearly_pro');
});

it('throws when product id is not configured', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => [],
        'yearly' => [],
    ]]);

    Log::shouldReceive('error')->once();

    $this->policy->resolve($this->workspace, $this->plan, BillingInterval::Monthly);
})->throws(InvalidArgumentException::class, 'Polar product ID is not configured for monthly pro plan.');

it('throws when product ids config is not an array', function (): void {
    config(['services.polar.product_ids' => null]);

    Log::shouldReceive('error')->once();

    $this->policy->resolve($this->workspace, $this->plan, BillingInterval::Monthly);
})->throws(InvalidArgumentException::class);

it('throws when interval ids is not an array', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => 'not-an-array',
    ]]);

    Log::shouldReceive('error')->once();

    $this->policy->resolve($this->workspace, $this->plan, BillingInterval::Monthly);
})->throws(InvalidArgumentException::class);

it('throws when product id is an empty string', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => ['pro' => ''],
    ]]);

    Log::shouldReceive('error')->once();

    $this->policy->resolve($this->workspace, $this->plan, BillingInterval::Monthly);
})->throws(InvalidArgumentException::class);

it('reports configured products when monthly ids exist', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => ['pro' => 'prod_abc'],
    ]]);

    expect($this->policy->hasConfiguredProducts())->toBeTrue();
});

it('reports configured products when yearly ids exist', function (): void {
    config(['services.polar.product_ids' => [
        'yearly' => ['pro' => 'prod_xyz'],
    ]]);

    expect($this->policy->hasConfiguredProducts())->toBeTrue();
});

it('reports no configured products when both are empty', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => [],
        'yearly' => [],
    ]]);

    expect($this->policy->hasConfiguredProducts())->toBeFalse();
});

it('reports no configured products when config is not an array', function (): void {
    config(['services.polar.product_ids' => null]);

    expect($this->policy->hasConfiguredProducts())->toBeFalse();
});

it('reports no configured products when ids contain only empty strings', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => ['pro' => '', 'starter' => ''],
        'yearly' => ['pro' => ''],
    ]]);

    expect($this->policy->hasConfiguredProducts())->toBeFalse();
});

it('reports configured products when at least one valid id exists', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => ['pro' => '', 'starter' => 'prod_valid'],
        'yearly' => [],
    ]]);

    expect($this->policy->hasConfiguredProducts())->toBeTrue();
});

it('reports no configured products when interval key is not an array', function (): void {
    config(['services.polar.product_ids' => [
        'monthly' => 'not-an-array',
        'yearly' => 'also-not-array',
    ]]);

    expect($this->policy->hasConfiguredProducts())->toBeFalse();
});
