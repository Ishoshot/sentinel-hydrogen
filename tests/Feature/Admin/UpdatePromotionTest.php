<?php

declare(strict_types=1);

use App\Actions\Admin\Promotions\UpdatePromotion;
use App\Enums\Promotions\PromotionValueType;
use App\Models\Plan;
use App\Models\Promotion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('updates promotion name', function (): void {
    $promotion = Promotion::factory()->create(['name' => 'Old Name']);

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, ['name' => 'New Name']);

    expect($result->name)->toBe('New Name');

    $this->assertDatabaseHas('promotions', [
        'id' => $promotion->id,
        'name' => 'New Name',
    ]);
});

it('updates promotion code and uppercases it', function (): void {
    $promotion = Promotion::factory()->create(['code' => 'OLD-CODE']);

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, ['code' => 'new-code']);

    expect($result->code)->toBe('NEW-CODE');
});

it('updates promotion description to null', function (): void {
    $promotion = Promotion::factory()->create(['description' => 'Some description']);

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, ['description' => null]);

    expect($result->description)->toBeNull();
});

it('updates value type and amount', function (): void {
    $promotion = Promotion::factory()->percentage(20)->create();

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, [
        'value_type' => PromotionValueType::Flat,
        'value_amount' => 500,
    ]);

    expect($result->value_type)->toBe(PromotionValueType::Flat);
});

it('updates valid_from and valid_to', function (): void {
    $promotion = Promotion::factory()->create();

    $newFrom = now()->addWeek();
    $newTo = now()->addMonth();

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, [
        'valid_from' => $newFrom,
        'valid_to' => $newTo,
    ]);

    expect($result->valid_from->toDateString())->toBe($newFrom->toDateString())
        ->and($result->valid_to->toDateString())->toBe($newTo->toDateString());
});

it('updates max_uses to null', function (): void {
    $promotion = Promotion::factory()->limitedUses(100)->create();

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, ['max_uses' => null]);

    expect($result->max_uses)->toBeNull();
});

it('updates is_active field', function (): void {
    $promotion = Promotion::factory()->create(['is_active' => true]);

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, ['is_active' => false]);

    expect($result->is_active)->toBeFalse();
});

it('does not call polar api when syncToPolar is false', function (): void {
    Http::fake();

    $promotion = Promotion::factory()->create(['polar_discount_id' => 'polar-123']);

    config(['services.polar.access_token' => 'test-token']);

    $action = app(UpdatePromotion::class);
    $action->handle($promotion, ['name' => 'Updated'], syncToPolar: false);

    Http::assertNothingSent();
});

it('calls polar api when syncToPolar is true and polar is configured and has discount id', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 'polar-456'], 200),
    ]);

    $promotion = Promotion::factory()->create(['polar_discount_id' => 'polar-456']);

    config(['services.polar.access_token' => 'test-token']);

    $action = app(UpdatePromotion::class);
    $action->handle($promotion, ['name' => 'Synced Update'], syncToPolar: true);

    Http::assertSentCount(1);
});

it('does not call polar api when polar is not configured', function (): void {
    Http::fake();

    $promotion = Promotion::factory()->create(['polar_discount_id' => 'polar-789']);

    config(['services.polar.access_token' => '']);

    $action = app(UpdatePromotion::class);
    $action->handle($promotion, ['name' => 'No Sync'], syncToPolar: true);

    Http::assertNothingSent();
});

it('does not call polar api when promotion has no polar_discount_id', function (): void {
    Http::fake();

    $promotion = Promotion::factory()->notSynced()->create();

    config(['services.polar.access_token' => 'test-token']);

    $action = app(UpdatePromotion::class);
    $action->handle($promotion, ['name' => 'No Polar ID'], syncToPolar: true);

    Http::assertNothingSent();
});

it('still updates promotion when polar api call throws an exception', function (): void {
    Log::spy();

    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $promotion = Promotion::factory()->create(['polar_discount_id' => 'polar-error']);

    config(['services.polar.access_token' => 'test-token']);

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, ['name' => 'Still Updated'], syncToPolar: true);

    expect($result->name)->toBe('Still Updated');
});

it('handles updating with empty data array', function (): void {
    $promotion = Promotion::factory()->create(['name' => 'Unchanged']);

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, []);

    expect($result->name)->toBe('Unchanged');
});

it('updates multiple fields at once', function (): void {
    $promotion = Promotion::factory()->create();

    $action = app(UpdatePromotion::class);
    $result = $action->handle($promotion, [
        'name' => 'Multi Update',
        'code' => 'multi-promo',
        'is_active' => false,
        'description' => 'Updated description',
    ]);

    expect($result->name)->toBe('Multi Update')
        ->and($result->code)->toBe('MULTI-PROMO')
        ->and($result->is_active)->toBeFalse()
        ->and($result->description)->toBe('Updated description');
});

it('updates eligible plan ids and allows resetting to global', function (): void {
    $planA = Plan::factory()->create();
    $planB = Plan::factory()->create();
    $promotion = Promotion::factory()->create(['eligible_plan_ids' => null]);

    $action = app(UpdatePromotion::class);
    $scoped = $action->handle($promotion, ['eligible_plan_ids' => [$planA->id, $planB->id]]);

    expect($scoped->eligible_plan_ids)->toBe([$planA->id, $planB->id]);

    $global = $action->handle($promotion->fresh(), ['eligible_plan_ids' => null]);

    expect($global->eligible_plan_ids)->toBeNull();
});
