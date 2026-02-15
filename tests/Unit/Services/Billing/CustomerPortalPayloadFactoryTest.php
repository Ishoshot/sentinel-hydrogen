<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\Factories\CustomerPortalPayloadFactory;
use Illuminate\Support\Facades\Log;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->factory = new CustomerPortalPayloadFactory;
});

it('builds portal payload with customer id and return url', function (): void {
    $workspace = Workspace::factory()->create();
    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'polar_customer_id' => 'cust_abc123',
    ]);

    $payload = $this->factory->build($workspace, 'https://example.com/return');

    expect($payload)->toBe([
        'customer_id' => 'cust_abc123',
        'return_url' => 'https://example.com/return',
    ]);
});

it('builds portal payload without return url when null', function (): void {
    $workspace = Workspace::factory()->create();
    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'polar_customer_id' => 'cust_abc123',
    ]);

    $payload = $this->factory->build($workspace, null);

    expect($payload)->toBe(['customer_id' => 'cust_abc123']);
});

it('builds portal payload without return url when empty string', function (): void {
    $workspace = Workspace::factory()->create();
    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'polar_customer_id' => 'cust_abc123',
    ]);

    $payload = $this->factory->build($workspace, '');

    expect($payload)->toBe(['customer_id' => 'cust_abc123']);
});

it('throws InvalidArgumentException when no subscription exists', function (): void {
    Log::shouldReceive('warning')->once();

    $workspace = Workspace::factory()->create();

    $this->factory->build($workspace, null);
})->throws(InvalidArgumentException::class, 'Workspace does not have a Polar customer ID.');

it('throws InvalidArgumentException when polar_customer_id is null', function (): void {
    Log::shouldReceive('warning')->once();

    $workspace = Workspace::factory()->create();
    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'polar_customer_id' => null,
    ]);

    $this->factory->build($workspace, null);
})->throws(InvalidArgumentException::class, 'Workspace does not have a Polar customer ID.');

it('throws InvalidArgumentException when polar_customer_id is empty string', function (): void {
    Log::shouldReceive('warning')->once();

    $workspace = Workspace::factory()->create();
    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'polar_customer_id' => '',
    ]);

    $this->factory->build($workspace, null);
})->throws(InvalidArgumentException::class, 'Workspace does not have a Polar customer ID.');
