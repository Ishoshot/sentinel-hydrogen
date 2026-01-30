<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Jobs\Briefings\ProcessBriefingGeneration;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config(['briefings.data_guard.enabled' => false]);

    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->workspace->teamMembers()->create([
        'user_id' => $this->user->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->briefing = Briefing::factory()->system()->create([
        'slug' => 'standup-update',
        'title' => 'Daily Standup Update',
        'is_active' => true,
    ]);
});

it('lists briefing generations for workspace', function (): void {
    BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->count(3)
        ->create(['generated_by_id' => $this->user->id]);

    // Create generation for another workspace - should not appear
    $otherWorkspace = Workspace::factory()->create();
    BriefingGeneration::factory()
        ->forWorkspace($otherWorkspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.index', $this->workspace));

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters briefing generations by status', function (): void {
    BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create(['generated_by_id' => $this->user->id]);

    BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->processing()
        ->create(['generated_by_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.index', [
            $this->workspace,
            'status' => [BriefingGenerationStatus::Completed->value],
        ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', BriefingGenerationStatus::Completed->value);
});

it('shows a specific briefing generation', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create(['generated_by_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.show', [$this->workspace, $generation]));

    $response->assertOk()
        ->assertJsonPath('data.id', $generation->id)
        ->assertJsonPath('data.status', BriefingGenerationStatus::Completed->value)
        ->assertJsonPath('data.workspace_id', $this->workspace->id)
        ->assertJsonPath('data.generated_by_id', $this->user->id)
        ->assertJsonPath('data.generated_by.id', $this->user->id)
        ->assertJsonPath('data.expires_at', $generation->expires_at?->toIso8601String());
});

it('generates a briefing and dispatches processing job', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]), [
            'parameters' => [
                'start_date' => now()->subWeek()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', BriefingGenerationStatus::Pending->value)
        ->assertJsonStructure([
            'data' => ['id', 'status', 'progress'],
            'message',
        ]);

    Queue::assertPushed(ProcessBriefingGeneration::class);

    expect(BriefingGeneration::where('workspace_id', $this->workspace->id)->count())->toBe(1);

    $generation = BriefingGeneration::query()->where('workspace_id', $this->workspace->id)->firstOrFail();

    expect($generation->expires_at)->not()->toBeNull()
        ->and($generation->expires_at?->greaterThan(now()))->toBeTrue();
});

it('rejects briefing generation when date range exceeds configured limit', function (): void {
    config()->set('briefings.limits.max_date_range_days', 3);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]), [
            'parameters' => [
                'start_date' => now()->subDays(10)->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

it('rejects briefing generation when repository limit is exceeded', function (): void {
    config()->set('briefings.limits.max_repositories', 1);

    $repoOne = Repository::factory()->create(['workspace_id' => $this->workspace->id]);
    $repoTwo = Repository::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]), [
            'parameters' => [
                'repository_ids' => [$repoOne->id, $repoTwo->id],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['repository_ids']);
});

it('returns 404 when generating for non-existent briefing', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, 'non-existent-slug']));

    $response->assertNotFound();
});

it('returns 404 when showing generation from another workspace', function (): void {
    $otherWorkspace = Workspace::factory()->create();
    $generation = BriefingGeneration::factory()
        ->forWorkspace($otherWorkspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.show', [$this->workspace, $generation]));

    $response->assertNotFound();
});

it('requires authentication to generate briefing', function (): void {
    $response = $this->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]));

    $response->assertUnauthorized();
});

it('requires workspace membership to generate briefing', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]));

    $response->assertForbidden();
});

it('paginates briefing generations', function (): void {
    BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->count(25)
        ->create(['generated_by_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.index', [$this->workspace, 'per_page' => 10]));

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 25);
});
