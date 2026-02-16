<?php

declare(strict_types=1);

use App\Actions\SentinelConfig\Handlers\RepositorySentinelConfigGuidelineHandler;
use App\Enums\Auth\ProviderType;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Repository;
use App\Services\SentinelConfig\ValueObjects\GuidelineConfig;
use App\Services\SentinelConfig\ValueObjects\SentinelConfig;

beforeEach(function (): void {
    $this->provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('allows guidelines when plan supports custom guidelines', function (): void {
    $plan = Plan::factory()->illuminate()->create();
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $repository->workspace->update([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $config = new SentinelConfig(
        version: 1,
        guidelines: [new GuidelineConfig(path: 'docs/STANDARDS.md', description: 'Standards')],
    );

    $handler = app(RepositorySentinelConfigGuidelineHandler::class);
    $result = $handler->apply($repository->fresh(), $config);

    expect($result['error'])->toBeNull()
        ->and($result['config']->guidelines)->toHaveCount(1)
        ->and($result['config']->guidelines[0]->path)->toBe('docs/STANDARDS.md');
});

it('strips guidelines and returns error when plan does not support custom guidelines', function (): void {
    $plan = Plan::factory()->create([
        'features' => [PlanFeature::CustomGuidelines->value => false],
    ]);
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $repository->workspace->update([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $config = new SentinelConfig(
        version: 1,
        guidelines: [new GuidelineConfig(path: 'docs/GUIDELINES.md')],
    );

    $handler = app(RepositorySentinelConfigGuidelineHandler::class);
    $result = $handler->apply($repository->fresh(), $config);

    expect($result['error'])->toBe('Custom guidelines are not available on your current plan.')
        ->and($result['config']->guidelines)->toBe([]);
});

it('preserves other config properties when stripping guidelines', function (): void {
    $plan = Plan::factory()->create([
        'features' => [PlanFeature::CustomGuidelines->value => false],
    ]);
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $repository->workspace->update([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $config = new SentinelConfig(
        version: 1,
        guidelines: [new GuidelineConfig(path: 'docs/X.md')],
    );

    $handler = app(RepositorySentinelConfigGuidelineHandler::class);
    $result = $handler->apply($repository->fresh(), $config);

    expect($result['config']->version)->toBe(1)
        ->and($result['config']->guidelines)->toBe([]);
});

it('passes through config unchanged when guidelines are empty', function (): void {
    $plan = Plan::factory()->create([
        'features' => [PlanFeature::CustomGuidelines->value => false],
    ]);
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $repository->workspace->update([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $config = new SentinelConfig(
        version: 1,
        guidelines: [],
    );

    $handler = app(RepositorySentinelConfigGuidelineHandler::class);
    $result = $handler->apply($repository->fresh(), $config);

    expect($result['error'])->toBeNull()
        ->and($result['config']->guidelines)->toBe([]);
});

it('passes through config when repository workspace relation returns null', function (): void {
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    // Force workspace to null to simulate orphaned repository
    $repository->setRelation('workspace', null);

    $config = new SentinelConfig(
        version: 1,
        guidelines: [new GuidelineConfig(path: 'docs/STYLE.md')],
    );

    $handler = app(RepositorySentinelConfigGuidelineHandler::class);
    $result = $handler->apply($repository, $config);

    expect($result['error'])->toBeNull()
        ->and($result['config']->guidelines)->toHaveCount(1);
});

it('allows multiple guidelines when plan supports them', function (): void {
    $plan = Plan::factory()->orchestrate()->create();
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $repository->workspace->update([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $config = new SentinelConfig(
        version: 1,
        guidelines: [
            new GuidelineConfig(path: 'docs/STYLE.md'),
            new GuidelineConfig(path: 'docs/SECURITY.md'),
            new GuidelineConfig(path: 'docs/TESTING.md'),
        ],
    );

    $handler = app(RepositorySentinelConfigGuidelineHandler::class);
    $result = $handler->apply($repository->fresh(), $config);

    expect($result['error'])->toBeNull()
        ->and($result['config']->guidelines)->toHaveCount(3);
});
