<?php

declare(strict_types=1);

use App\Actions\Subscriptions\Handlers\PromotionHandler;
use App\Actions\Subscriptions\Support\TransitionDirection;
use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// --- validateIfApplicable ---

it('returns null when direction does not accept promotions', function (): void {
    $handler = app(PromotionHandler::class);

    $result = $handler->validateIfApplicable(TransitionDirection::Cancel, 'PROMO-CODE');

    expect($result)->toBeNull();
});

it('returns null when direction is downgrade', function (): void {
    $handler = app(PromotionHandler::class);

    $result = $handler->validateIfApplicable(TransitionDirection::Downgrade, 'PROMO-CODE');

    expect($result)->toBeNull();
});

it('returns null when promo code is null', function (): void {
    $handler = app(PromotionHandler::class);

    $result = $handler->validateIfApplicable(TransitionDirection::Subscribe, null);

    expect($result)->toBeNull();
});

it('returns null when promo code is empty string', function (): void {
    $handler = app(PromotionHandler::class);

    $result = $handler->validateIfApplicable(TransitionDirection::Subscribe, '');

    expect($result)->toBeNull();
});

it('returns promotion on valid subscribe promo code', function (): void {
    $promotion = Promotion::factory()->create();
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);
    $handler = app(PromotionHandler::class);

    $result = $handler->validateIfApplicable(TransitionDirection::Subscribe, $promotion->code, $targetPlan);

    expect($result)->toBeInstanceOf(Promotion::class)
        ->and($result->id)->toBe($promotion->id);
});

it('returns promotion on valid upgrade promo code', function (): void {
    $promotion = Promotion::factory()->create();
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);
    $handler = app(PromotionHandler::class);

    $result = $handler->validateIfApplicable(TransitionDirection::Upgrade, $promotion->code, $targetPlan);

    expect($result)->toBeInstanceOf(Promotion::class)
        ->and($result->id)->toBe($promotion->id);
});

it('throws exception on invalid promo code', function (): void {
    $handler = app(PromotionHandler::class);
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);

    $handler->validateIfApplicable(TransitionDirection::Subscribe, 'INVALID', $targetPlan);
})->throws(InvalidArgumentException::class, 'Invalid promotion code.');

it('throws exception for expired promotion', function (): void {
    Promotion::factory()->expired()->create(['code' => 'EXPIRED']);
    $handler = app(PromotionHandler::class);
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);

    $handler->validateIfApplicable(TransitionDirection::Subscribe, 'EXPIRED', $targetPlan);
})->throws(InvalidArgumentException::class);

it('throws exception for inactive promotion', function (): void {
    Promotion::factory()->inactive()->create(['code' => 'INACTIVE']);
    $handler = app(PromotionHandler::class);
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);

    $handler->validateIfApplicable(TransitionDirection::Subscribe, 'INACTIVE', $targetPlan);
})->throws(InvalidArgumentException::class);

it('throws exception for promotion not synced to polar', function (): void {
    Promotion::factory()->notSynced()->create(['code' => 'NOTSYNC']);
    $handler = app(PromotionHandler::class);
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);

    $handler->validateIfApplicable(TransitionDirection::Subscribe, 'NOTSYNC', $targetPlan);
})->throws(InvalidArgumentException::class);

it('throws exception when promotion is not eligible for the target plan', function (): void {
    $eligiblePlan = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);
    $targetPlan = Plan::factory()->create(['tier' => PlanTier::Orchestrate->value]);
    Promotion::factory()->forPlans([$eligiblePlan->id])->create(['code' => 'SCOPED-NOT-ELIGIBLE']);

    $handler = app(PromotionHandler::class);
    $handler->validateIfApplicable(TransitionDirection::Subscribe, 'SCOPED-NOT-ELIGIBLE', $targetPlan);
})->throws(InvalidArgumentException::class, 'This promotion code is not valid for the selected plan.');

// --- recordPendingCheckout ---

it('records pending checkout when promotion is provided', function (): void {
    $workspace = Workspace::factory()->create();
    $promotion = Promotion::factory()->create();
    $checkoutUrl = 'https://checkout.example.com/session/123';
    $handler = app(PromotionHandler::class);

    $handler->recordPendingCheckout($workspace, $promotion, $checkoutUrl);

    expect(PromotionUsage::query()
        ->where('workspace_id', $workspace->id)
        ->where('promotion_id', $promotion->id)
        ->where('checkout_url', $checkoutUrl)
        ->exists()
    )->toBeTrue();
});

it('does not record pending checkout when promotion is null', function (): void {
    $workspace = Workspace::factory()->create();
    $handler = app(PromotionHandler::class);

    $handler->recordPendingCheckout($workspace, null, 'https://checkout.example.com/session/123');

    expect(PromotionUsage::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

// --- recordCompleted ---

it('records completed usage when promotion is provided', function (): void {
    $workspace = Workspace::factory()->create();
    $promotion = Promotion::factory()->create();
    $subscription = Subscription::factory()->for($workspace)->create();
    $handler = app(PromotionHandler::class);

    $handler->recordCompleted($workspace, $promotion, $subscription);

    expect(PromotionUsage::query()
        ->where('workspace_id', $workspace->id)
        ->where('promotion_id', $promotion->id)
        ->where('subscription_id', $subscription->id)
        ->exists()
    )->toBeTrue();
});

it('does not record completed usage when promotion is null', function (): void {
    $workspace = Workspace::factory()->create();
    $subscription = Subscription::factory()->for($workspace)->create();
    $handler = app(PromotionHandler::class);

    $handler->recordCompleted($workspace, null, $subscription);

    expect(PromotionUsage::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('increments promotion usage count on completed recording', function (): void {
    $workspace = Workspace::factory()->create();
    $promotion = Promotion::factory()->create(['times_used' => 5]);
    $subscription = Subscription::factory()->for($workspace)->create();
    $handler = app(PromotionHandler::class);

    $handler->recordCompleted($workspace, $promotion, $subscription);

    expect($promotion->fresh()->times_used)->toBe(6);
});
