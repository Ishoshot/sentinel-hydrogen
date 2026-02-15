<?php

declare(strict_types=1);

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;
use App\Services\Billing\Factories\CheckoutPayloadFactory;

beforeEach(function (): void {
    $this->factory = new CheckoutPayloadFactory;
    $this->workspace = (new Workspace)->forceFill(['id' => 42, 'name' => 'Test Workspace']);
    $this->plan = (new Plan)->forceFill(['id' => 1, 'tier' => 'pro']);
});

it('builds basic checkout payload without optional fields', function (): void {
    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: null,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload['products'])->toBe(['prod_abc123']);
    expect($payload['allow_discount_codes'])->toBeTrue();
    expect($payload['metadata'])->toBe([
        'workspace_id' => '42',
        'plan_tier' => 'pro',
        'billing_interval' => 'monthly',
    ]);
    expect($payload)->not->toHaveKey('success_url');
    expect($payload)->not->toHaveKey('discount_id');
    expect($payload)->not->toHaveKey('customer_email');
});

it('includes success_url when provided', function (): void {
    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: null,
        successUrl: 'https://example.com/success',
        customerEmail: null,
    );

    expect($payload['success_url'])->toBe('https://example.com/success');
});

it('does not include success_url when empty string', function (): void {
    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: null,
        successUrl: '',
        customerEmail: null,
    );

    expect($payload)->not->toHaveKey('success_url');
});

it('includes customer_email when provided', function (): void {
    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: null,
        successUrl: null,
        customerEmail: 'user@example.com',
    );

    expect($payload['customer_email'])->toBe('user@example.com');
});

it('does not include customer_email when empty string', function (): void {
    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: null,
        successUrl: null,
        customerEmail: '',
    );

    expect($payload)->not->toHaveKey('customer_email');
});

it('includes discount_id for valid promotion with polar_discount_id', function (): void {
    $promotion = (new Promotion)->forceFill([
        'id' => 7,
        'polar_discount_id' => 'disc_xyz789',
        'is_active' => true,
        'valid_from' => null,
        'valid_to' => null,
        'max_uses' => null,
        'times_used' => 0,
    ]);

    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: $promotion,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload['discount_id'])->toBe('disc_xyz789');
    expect($payload['metadata']['promotion_id'])->toBe('7');
});

it('does not include discount_id for invalid promotion', function (): void {
    $promotion = (new Promotion)->forceFill([
        'id' => 7,
        'polar_discount_id' => 'disc_xyz789',
        'is_active' => false,
        'valid_from' => null,
        'valid_to' => null,
        'max_uses' => null,
        'times_used' => 0,
    ]);

    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: $promotion,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload)->not->toHaveKey('discount_id');
});

it('does not include discount_id when promotion has null polar_discount_id', function (): void {
    $promotion = (new Promotion)->forceFill([
        'id' => 7,
        'polar_discount_id' => null,
        'is_active' => true,
        'valid_from' => null,
        'valid_to' => null,
        'max_uses' => null,
        'times_used' => 0,
    ]);

    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: $promotion,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload)->not->toHaveKey('discount_id');
});

it('uses yearly billing interval in metadata', function (): void {
    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Yearly,
        promotion: null,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload['metadata']['billing_interval'])->toBe('yearly');
});

it('includes promotion_id in metadata when promotion has an id', function (): void {
    $promotion = (new Promotion)->forceFill([
        'id' => 10,
        'polar_discount_id' => null,
        'is_active' => false,
        'valid_from' => null,
        'valid_to' => null,
        'max_uses' => null,
        'times_used' => 0,
    ]);

    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: $promotion,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload['metadata']['promotion_id'])->toBe('10');
});

it('does not include promotion_id in metadata when promotion id is null', function (): void {
    $promotion = (new Promotion)->forceFill([
        'id' => null,
        'polar_discount_id' => null,
        'is_active' => false,
        'valid_from' => null,
        'valid_to' => null,
        'max_uses' => null,
        'times_used' => 0,
    ]);

    $payload = $this->factory->build(
        productId: 'prod_abc123',
        workspace: $this->workspace,
        plan: $this->plan,
        interval: BillingInterval::Monthly,
        promotion: $promotion,
        successUrl: null,
        customerEmail: null,
    );

    expect($payload['metadata'])->not->toHaveKey('promotion_id');
});
