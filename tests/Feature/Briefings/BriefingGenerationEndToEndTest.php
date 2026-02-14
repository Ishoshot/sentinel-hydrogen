<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Events\Briefings\BriefingGenerationCompleted;
use App\Events\Briefings\BriefingGenerationFailed;
use App\Events\Briefings\BriefingGenerationProgress;
use App\Events\Briefings\BriefingGenerationStarted;
use App\Jobs\Briefings\ProcessBriefingGeneration;
use App\Jobs\Briefings\RenderBriefingPdf;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\Contracts\BriefingNarrativeGenerator;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingExcerpts;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\NarrativeGenerationResult;
use App\Services\Briefings\ValueObjects\NarrativeGenerationTelemetry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
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
        'requires_ai' => true,
        'prompt_path' => 'briefings.prompts.default',
        'is_active' => true,
    ]);

    // Create a repository and some runs so data collection has real data
    $this->repository = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    Run::factory()
        ->completed()
        ->count(5)
        ->forRepository($this->repository)
        ->create([
            'pr_number' => fake()->numberBetween(1, 999),
            'pr_title' => fake()->sentence(),
        ]);
});

it('completes the full generation flow from API request through job processing', function (): void {
    Event::fake([
        BriefingGenerationStarted::class,
        BriefingGenerationCompleted::class,
        BriefingGenerationProgress::class,
    ]);

    // Fake both jobs: ProcessBriefingGeneration so the API only creates the record,
    // and RenderBriefingPdf so we can assert it gets dispatched during processing.
    Queue::fake([ProcessBriefingGeneration::class, RenderBriefingPdf::class]);

    $narrativeText = 'The team had a productive week with 5 completed reviews across the repository.';

    $mockGenerator = Mockery::mock(BriefingNarrativeGenerator::class);
    $mockGenerator->shouldReceive('generate')
        ->once()
        ->andReturn(new NarrativeGenerationResult(
            text: $narrativeText,
            telemetry: new NarrativeGenerationTelemetry(
                provider: 'anthropic',
                model: 'claude-sonnet-4-5-20250929',
                promptTokens: 500,
                completionTokens: 200,
                totalTokens: 700,
                durationMs: 3200,
            ),
        ));
    $mockGenerator->shouldReceive('generateExcerpts')
        ->once()
        ->andReturn(BriefingExcerpts::fromArray([
            'short' => '5 completed, 0 in progress',
            'slack' => '*Team Update* - 5 reviews completed',
            'email' => 'Weekly summary: 5 reviews completed.',
            'linkedin' => 'Our team shipped 5 improvements.',
        ]));

    $this->app->instance(BriefingNarrativeGenerator::class, $mockGenerator);

    // Step 1: Hit the API endpoint to create the generation
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

    // Verify the ProcessBriefingGeneration job was dispatched
    Queue::assertPushed(ProcessBriefingGeneration::class);

    $generationId = $response->json('data.id');
    $generation = BriefingGeneration::findOrFail($generationId);

    expect($generation->status)->toBe(BriefingGenerationStatus::Pending)
        ->and($generation->workspace_id)->toBe($this->workspace->id)
        ->and($generation->briefing_id)->toBe($this->briefing->id)
        ->and($generation->generated_by_id)->toBe($this->user->id);

    // Step 2: Manually invoke the job handle to simulate queue processing
    app()->call([new ProcessBriefingGeneration($generation), 'handle']);

    // Step 3: Reload and verify generation is completed
    $generation->refresh();

    expect($generation->status)->toBe(BriefingGenerationStatus::Completed)
        ->and($generation->progress)->toBe(100)
        ->and($generation->narrative)->toBe($narrativeText)
        ->and($generation->completed_at)->not()->toBeNull()
        ->and($generation->started_at)->not()->toBeNull()
        ->and($generation->error_message)->toBeNull();

    // Step 4: Verify metadata contains byok and ai_telemetry
    expect($generation->metadata)->toBeArray()
        ->and($generation->metadata)->toHaveKey('byok')
        ->and($generation->metadata['byok'])->toBeFalse()
        ->and($generation->metadata)->toHaveKey('ai_telemetry')
        ->and($generation->metadata['ai_telemetry']['provider'])->toBe('anthropic')
        ->and($generation->metadata['ai_telemetry']['model'])->toBe('claude-sonnet-4-5-20250929')
        ->and($generation->metadata['ai_telemetry']['prompt_tokens'])->toBe(500)
        ->and($generation->metadata['ai_telemetry']['completion_tokens'])->toBe(200)
        ->and($generation->metadata['ai_telemetry']['total_tokens'])->toBe(700)
        ->and($generation->metadata['ai_telemetry']['duration_ms'])->toBe(3200);

    // Step 5: Verify excerpts are generated
    expect($generation->excerpts)->toBeArray()
        ->and($generation->excerpts)->toHaveKey('short')
        ->and($generation->excerpts)->toHaveKey('slack')
        ->and($generation->excerpts)->toHaveKey('email')
        ->and($generation->excerpts)->toHaveKey('linkedin')
        ->and($generation->excerpts['short'])->toBe('5 completed, 0 in progress');

    // Step 6: Verify structured data and slides are stored
    expect($generation->structured_data)->toBeArray()
        ->and($generation->structured_data)->toHaveKey('period')
        ->and($generation->structured_data)->toHaveKey('summary')
        ->and($generation->structured_data)->toHaveKey('slides');

    // Step 7: Verify achievements are stored
    expect($generation->achievements)->toBeArray();

    // Step 8: Verify events were dispatched
    Event::assertDispatched(BriefingGenerationStarted::class, function (BriefingGenerationStarted $event) use ($generation): bool {
        return $event->generation->id === $generation->id;
    });

    Event::assertDispatched(BriefingGenerationCompleted::class, function (BriefingGenerationCompleted $event) use ($generation): bool {
        return $event->generation->id === $generation->id;
    });

    Event::assertDispatched(BriefingGenerationProgress::class);

    // Step 9: Verify the render job was dispatched
    Queue::assertPushed(RenderBriefingPdf::class, function (RenderBriefingPdf $job) use ($generation): bool {
        return $job->generation->id === $generation->id;
    });
});

it('handles AI failure gracefully during generation', function (): void {
    Event::fake([
        BriefingGenerationStarted::class,
        BriefingGenerationFailed::class,
        BriefingGenerationProgress::class,
    ]);

    Queue::fake([ProcessBriefingGeneration::class, RenderBriefingPdf::class]);

    $mockGenerator = Mockery::mock(BriefingNarrativeGenerator::class);
    $mockGenerator->shouldReceive('generate')
        ->once()
        ->andThrow(new RuntimeException('AI provider is unavailable'));

    $this->app->instance(BriefingNarrativeGenerator::class, $mockGenerator);

    // Create the generation via the API
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]), [
            'parameters' => [
                'start_date' => now()->subWeek()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ]);

    $response->assertCreated();

    $generationId = $response->json('data.id');
    $generation = BriefingGeneration::findOrFail($generationId);

    // Run the job and expect it to throw (the job re-throws after recording failure)
    try {
        app()->call([new ProcessBriefingGeneration($generation), 'handle']);
    } catch (RuntimeException) {
        // Expected: the job re-throws after handleFailure
    }

    // Verify the generation status is Failed
    $generation->refresh();

    expect($generation->status)->toBe(BriefingGenerationStatus::Failed)
        ->and($generation->error_message)->not()->toBeNull()
        ->and($generation->error_message)->toContain('AI provider is unavailable')
        ->and($generation->narrative)->toBeNull();

    // Verify the render job was NOT dispatched
    Queue::assertNotPushed(RenderBriefingPdf::class);

    // Verify failure event was dispatched
    Event::assertDispatched(BriefingGenerationFailed::class, function (BriefingGenerationFailed $event) use ($generation): bool {
        return $event->generation->id === $generation->id;
    });

    // Started should have been dispatched before the failure
    Event::assertDispatched(BriefingGenerationStarted::class);
});

it('uses BYOK key when workspace has a provider key configured', function (): void {
    Event::fake([
        BriefingGenerationStarted::class,
        BriefingGenerationCompleted::class,
        BriefingGenerationProgress::class,
    ]);

    Queue::fake([ProcessBriefingGeneration::class, RenderBriefingPdf::class]);

    // Create a workspace-level BYOK provider key
    ProviderKey::factory()
        ->forWorkspaceLevel($this->workspace)
        ->anthropic()
        ->create();

    $narrativeText = 'BYOK-generated narrative content for the team.';

    $mockGenerator = Mockery::mock(BriefingNarrativeGenerator::class);
    $mockGenerator->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $promptPath, BriefingStructuredData $data, BriefingAchievements $achievements, $aiConfig): bool {
            // Verify the AI config indicates BYOK
            return $aiConfig->isByok === true;
        })
        ->andReturn(new NarrativeGenerationResult(
            text: $narrativeText,
            telemetry: new NarrativeGenerationTelemetry(
                provider: 'anthropic',
                model: 'claude-sonnet-4-5-20250929',
                promptTokens: 300,
                completionTokens: 150,
                totalTokens: 450,
                durationMs: 2500,
            ),
        ));
    $mockGenerator->shouldReceive('generateExcerpts')
        ->once()
        ->andReturn(BriefingExcerpts::fromArray([
            'short' => '5 completed, 0 in progress',
            'slack' => '*Team Update* - 5 reviews completed',
            'email' => 'Summary from BYOK generation.',
            'linkedin' => 'Our team shipped 5 improvements.',
        ]));

    $this->app->instance(BriefingNarrativeGenerator::class, $mockGenerator);

    // Create the generation via the API
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefings.generate', [$this->workspace, $this->briefing->slug]), [
            'parameters' => [
                'start_date' => now()->subWeek()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ]);

    $response->assertCreated();

    $generationId = $response->json('data.id');
    $generation = BriefingGeneration::findOrFail($generationId);

    // Process the generation
    app()->call([new ProcessBriefingGeneration($generation), 'handle']);

    $generation->refresh();

    // Verify the generation completed with BYOK metadata
    expect($generation->status)->toBe(BriefingGenerationStatus::Completed)
        ->and($generation->narrative)->toBe($narrativeText)
        ->and($generation->metadata)->toBeArray()
        ->and($generation->metadata['byok'])->toBeTrue()
        ->and($generation->metadata['ai_telemetry'])->toBeArray()
        ->and($generation->metadata['ai_telemetry']['provider'])->toBe('anthropic');

    // Verify events
    Event::assertDispatched(BriefingGenerationCompleted::class);
    Queue::assertPushed(RenderBriefingPdf::class);
});
