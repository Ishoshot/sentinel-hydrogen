<?php

declare(strict_types=1);

use App\Actions\Admin\Promotions\DeletePromotion;
use App\Models\Promotion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('deletes a promotion from the database', function (): void {
    $promotion = Promotion::factory()->create();

    $action = app(DeletePromotion::class);
    $action->handle($promotion);

    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});

it('deletes a promotion without polar_discount_id', function (): void {
    $promotion = Promotion::factory()->notSynced()->create();

    $action = app(DeletePromotion::class);
    $action->handle($promotion);

    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});

it('does not call polar api when syncToPolar is false', function (): void {
    Http::fake();

    $promotion = Promotion::factory()->create([
        'polar_discount_id' => 'polar-123',
    ]);

    config(['services.polar.access_token' => 'test-token']);

    $action = app(DeletePromotion::class);
    $action->handle($promotion, syncToPolar: false);

    Http::assertNothingSent();

    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});

it('does not call polar api when polar is not configured', function (): void {
    Http::fake();

    $promotion = Promotion::factory()->create([
        'polar_discount_id' => 'polar-789',
    ]);

    config(['services.polar.access_token' => '']);

    $action = app(DeletePromotion::class);
    $action->handle($promotion, syncToPolar: true);

    Http::assertNothingSent();

    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});

it('calls polar api to delete discount when syncToPolar is true and polar is configured', function (): void {
    Http::fake([
        '*' => Http::response(null, 204),
    ]);

    $promotion = Promotion::factory()->create([
        'polar_discount_id' => 'polar-456',
    ]);

    config(['services.polar.access_token' => 'test-token']);

    $action = app(DeletePromotion::class);
    $action->handle($promotion, syncToPolar: true);

    Http::assertSentCount(1);

    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});

it('still deletes promotion when polar api call throws an exception', function (): void {
    Log::spy();

    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $promotion = Promotion::factory()->create([
        'polar_discount_id' => 'polar-error',
    ]);

    config(['services.polar.access_token' => 'test-token']);

    $action = app(DeletePromotion::class);
    $action->handle($promotion, syncToPolar: true);

    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});
