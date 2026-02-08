<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;

beforeEach(function (): void {
    $this->admin = Admin::factory()->create();
});

describe('index', function (): void {
    it('requires authentication', function (): void {
        $this->getJson(route('admin.briefings.index'))
            ->assertUnauthorized();
    });

    it('lists all briefings', function (): void {
        Briefing::factory()->count(3)->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.briefings.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'slug', 'description', 'icon', 'is_system', 'is_active', 'sort_order'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('filters by active_only', function (): void {
        Briefing::factory()->count(2)->create(['is_active' => true]);
        Briefing::factory()->inactive()->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.briefings.index', ['active_only' => true]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('filters by system_only', function (): void {
        Briefing::factory()->count(2)->system()->create();
        Briefing::factory()->create(['is_system' => false]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.briefings.index', ['system_only' => true]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('paginates results', function (): void {
        Briefing::factory()->count(5)->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.briefings.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2);
    });
});

describe('store', function (): void {
    it('creates a briefing', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.briefings.store'), [
                'title' => 'Weekly Sprint Review',
                'slug' => 'weekly-sprint-review',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Weekly Sprint Review')
            ->assertJsonPath('data.slug', 'weekly-sprint-review')
            ->assertJsonPath('message', 'Briefing created successfully.');

        $this->assertDatabaseHas('briefings', [
            'title' => 'Weekly Sprint Review',
            'slug' => 'weekly-sprint-review',
        ]);
    });

    it('validates required fields', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.briefings.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'slug']);
    });

    it('validates unique slug', function (): void {
        Briefing::factory()->create(['slug' => 'existing-slug']);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.briefings.store'), [
                'title' => 'New Briefing',
                'slug' => 'existing-slug',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });

    it('creates with all optional fields', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.briefings.store'), [
                'title' => 'Full Briefing',
                'slug' => 'full-briefing',
                'description' => 'A fully configured briefing',
                'icon' => 'chart-bar',
                'target_roles' => ['engineering_manager'],
                'parameter_schema' => ['type' => 'object'],
                'prompt_path' => 'briefings.prompts.custom',
                'requires_ai' => true,
                'eligible_plan_ids' => [1, 2],
                'output_formats' => ['html', 'pdf'],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'A fully configured briefing')
            ->assertJsonPath('data.icon', 'chart-bar')
            ->assertJsonPath('data.target_roles', ['engineering_manager'])
            ->assertJsonPath('data.prompt_path', 'briefings.prompts.custom')
            ->assertJsonPath('data.requires_ai', true)
            ->assertJsonPath('data.eligible_plan_ids', [1, 2])
            ->assertJsonPath('data.output_formats', ['html', 'pdf'])
            ->assertJsonPath('data.is_schedulable', true)
            ->assertJsonPath('data.is_system', true)
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.is_active', true);
    });
});

describe('show', function (): void {
    it('returns a single briefing with counts', function (): void {
        $briefing = Briefing::factory()->create(['title' => 'Show Test']);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.briefings.show', ['briefing' => $briefing]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Show Test')
            ->assertJsonPath('data.generations_count', 0)
            ->assertJsonPath('data.subscriptions_count', 0);
    });

    it('returns 404 for non-existent briefing', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.briefings.show', ['briefing' => 99999]))
            ->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates a briefing', function (): void {
        $briefing = Briefing::factory()->create(['title' => 'Original']);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.briefings.update', ['briefing' => $briefing]), [
                'title' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('message', 'Briefing updated successfully.');

        $this->assertDatabaseHas('briefings', [
            'id' => $briefing->id,
            'title' => 'Updated',
        ]);
    });

    it('validates unique slug ignoring self', function (): void {
        $briefing = Briefing::factory()->create(['slug' => 'my-slug']);
        Briefing::factory()->create(['slug' => 'taken-slug']);

        // Updating to own slug should succeed
        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.briefings.update', ['briefing' => $briefing]), [
                'slug' => 'my-slug',
            ])
            ->assertOk();

        // Updating to another briefing's slug should fail
        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.briefings.update', ['briefing' => $briefing]), [
                'slug' => 'taken-slug',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });

    it('performs partial update', function (): void {
        $briefing = Briefing::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.briefings.update', ['briefing' => $briefing]), [
                'title' => 'New Title',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.description', 'Original Description');
    });

    it('allows setting nullable fields to null', function (): void {
        $briefing = Briefing::factory()->create([
            'description' => 'Has a description',
            'icon' => 'chart-bar',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.briefings.update', ['briefing' => $briefing]), [
                'description' => null,
                'icon' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.icon', null);
    });
});

describe('destroy', function (): void {
    it('deletes a briefing', function (): void {
        $briefing = Briefing::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('admin.briefings.destroy', ['briefing' => $briefing]))
            ->assertOk()
            ->assertJsonPath('message', 'Briefing deleted successfully.');

        $this->assertDatabaseMissing('briefings', ['id' => $briefing->id]);
    });

    it('prevents deletion when generations exist', function (): void {
        $briefing = Briefing::factory()->create();
        BriefingGeneration::factory()->forBriefing($briefing)->create();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('admin.briefings.destroy', ['briefing' => $briefing]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete this briefing as it has 1 generation(s).');

        $this->assertDatabaseHas('briefings', ['id' => $briefing->id]);
    });

    it('prevents deletion when active subscriptions exist', function (): void {
        $briefing = Briefing::factory()->create();
        BriefingSubscription::factory()->create([
            'briefing_id' => $briefing->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('admin.briefings.destroy', ['briefing' => $briefing]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete this briefing as it has 1 active subscription(s).');

        $this->assertDatabaseHas('briefings', ['id' => $briefing->id]);
    });
});
