<?php

declare(strict_types=1);

use App\Actions\Subscriptions\RecordPromotionUsage;
use App\Enums\Promotions\PromotionUsageStatus;
use App\Models\Promotion;
use App\Models\Subscription;
use App\Models\Workspace;

it('records pending promotion usage for checkout', function (): void {
    $workspace = Workspace::factory()->create();
    $promotion = Promotion::factory()->create();

    $action = new RecordPromotionUsage;
    $action->pendingCheckout($workspace, $promotion, 'https://checkout.example.com');

    $this->assertDatabaseHas('promotion_usages', [
        'promotion_id' => $promotion->id,
        'workspace_id' => $workspace->id,
        'status' => PromotionUsageStatus::Pending->value,
        'checkout_url' => 'https://checkout.example.com',
    ]);
});

it('records completed promotion usage after subscription activation', function (): void {
    $workspace = Workspace::factory()->create();
    $promotion = Promotion::factory()->create(['times_used' => 0]);
    $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

    $action = new RecordPromotionUsage;
    $action->completed($workspace, $promotion, $subscription);

    $this->assertDatabaseHas('promotion_usages', [
        'promotion_id' => $promotion->id,
        'workspace_id' => $workspace->id,
        'subscription_id' => $subscription->id,
        'status' => PromotionUsageStatus::Completed->value,
    ]);

    $promotion->refresh();
    expect($promotion->times_used)->toBe(1);
});
