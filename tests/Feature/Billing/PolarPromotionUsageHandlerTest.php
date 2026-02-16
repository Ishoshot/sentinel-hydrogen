<?php

declare(strict_types=1);

use App\Actions\Billing\Handlers\PolarPromotionUsageHandler;
use App\Enums\Promotions\PromotionUsageStatus;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;

it('ignores non-string promotion id', function (): void {
    $workspace = Workspace::factory()->create();
    $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

    $handler = new PolarPromotionUsageHandler;
    $handler->confirm($workspace, $subscription, null);
    $handler->confirm($workspace, $subscription, 123);
    $handler->confirm($workspace, $subscription, '');

    expect(PromotionUsage::count())->toBe(0);
});

it('warns on invalid promotion id format', function (): void {
    $workspace = Workspace::factory()->create();
    $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

    $handler = new PolarPromotionUsageHandler;
    $handler->confirm($workspace, $subscription, 'not-a-digit');

    expect(PromotionUsage::count())->toBe(0);
});

it('confirms pending promotion usage', function (): void {
    $workspace = Workspace::factory()->create();
    $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);
    $promotion = Promotion::factory()->create();
    PromotionUsage::factory()->create([
        'workspace_id' => $workspace->id,
        'promotion_id' => $promotion->id,
        'status' => PromotionUsageStatus::Pending,
    ]);

    $handler = new PolarPromotionUsageHandler;
    $handler->confirm($workspace, $subscription, (string) $promotion->id);

    $usage = PromotionUsage::query()
        ->where('workspace_id', $workspace->id)
        ->where('promotion_id', $promotion->id)
        ->first();

    expect($usage->status)->toBe(PromotionUsageStatus::Completed);
});

it('does nothing when no pending usage exists', function (): void {
    $workspace = Workspace::factory()->create();
    $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

    $handler = new PolarPromotionUsageHandler;
    $handler->confirm($workspace, $subscription, '999');

    expect(true)->toBeTrue();
});
