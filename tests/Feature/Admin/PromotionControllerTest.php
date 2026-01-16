<?php

declare(strict_types=1);

use App\Enums\PromotionValueType;
use App\Models\Admin;
use App\Models\Promotion;

beforeEach(function (): void {
    $this->admin = Admin::factory()->create();
});

describe('index', function (): void {
    it('requires authentication', function (): void {
        $this->getJson(route('promotions.index'))
            ->assertUnauthorized();
    });

    it('lists all promotions', function (): void {
        Promotion::factory()->count(3)->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('promotions.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'value_type', 'value_amount', 'is_active'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('filters active promotions', function (): void {
        Promotion::factory()->count(2)->create(['is_active' => true]);
        Promotion::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('promotions.index', ['active_only' => true]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('store', function (): void {
    it('creates a promotion', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('promotions.store'), [
                'name' => 'Test Promo',
                'code' => 'TEST123',
                'value_type' => PromotionValueType::Percentage->value,
                'value_amount' => 20,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Promo')
            ->assertJsonPath('data.code', 'TEST123')
            ->assertJsonPath('message', 'Promotion created successfully.');

        $this->assertDatabaseHas('promotions', [
            'name' => 'Test Promo',
            'code' => 'TEST123',
        ]);
    });

    it('validates required fields', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('promotions.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'value_type', 'value_amount']);
    });

    it('validates unique code', function (): void {
        Promotion::factory()->create(['code' => 'EXISTING']);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('promotions.store'), [
                'name' => 'Test',
                'code' => 'EXISTING',
                'value_type' => PromotionValueType::Percentage->value,
                'value_amount' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });
});

describe('show', function (): void {
    it('returns a single promotion', function (): void {
        $promotion = Promotion::factory()->create(['name' => 'Show Test']);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('promotions.show', $promotion))
            ->assertOk()
            ->assertJsonPath('data.name', 'Show Test');
    });

    it('returns 404 for non-existent promotion', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('promotions.show', ['promotion' => 99999]))
            ->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates a promotion', function (): void {
        $promotion = Promotion::factory()->create(['name' => 'Original']);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('promotions.update', $promotion), [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonPath('message', 'Promotion updated successfully.');

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Updated',
        ]);
    });

    it('allows updating code to a unique value', function (): void {
        $promotion = Promotion::factory()->create(['code' => 'OLDCODE']);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('promotions.update', $promotion), [
                'code' => 'NEWCODE',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', 'NEWCODE');
    });
});

describe('destroy', function (): void {
    it('deletes a promotion', function (): void {
        $promotion = Promotion::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('promotions.destroy', $promotion))
            ->assertOk()
            ->assertJsonPath('message', 'Promotion deleted successfully.');

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    });
});
