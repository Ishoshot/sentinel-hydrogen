<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
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

    $this->briefing = Briefing::factory()->system()->create(['is_active' => true]);

    $this->generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create(['generated_by_id' => $this->user->id]);
});

it('downloads a completed generation in html format', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('briefings/1/1/html.html', '<h1>Test</h1>');

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $this->generation,
            'html',
        ]));

    $response->assertSuccessful()
        ->assertJsonStructure(['url', 'filename', 'content_type']);
});

it('rejects download of incomplete generation', function (): void {
    $pendingGeneration = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->pending()
        ->create(['generated_by_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $pendingGeneration,
            'html',
        ]));

    $response->assertBadRequest()
        ->assertJson(['message' => 'Briefing generation is not yet complete.']);
});

it('rejects invalid format', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $this->generation,
            'docx',
        ]));

    $response->assertBadRequest()
        ->assertJsonFragment(['message' => 'Invalid format. Supported formats: html, pdf, markdown, slides']);
});

it('returns 404 when format path does not exist on generation', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $this->generation,
            'markdown',
        ]));

    $response->assertNotFound()
        ->assertJson(['message' => 'This format is not available for this briefing.']);
});

it('returns 404 for generation from another workspace', function (): void {
    $otherWorkspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $otherGeneration = BriefingGeneration::factory()
        ->forWorkspace($otherWorkspace)
        ->completed()
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $otherGeneration,
            'html',
        ]));

    $response->assertNotFound();
});

it('requires authentication to download', function (): void {
    $response = $this->getJson(route('briefing-generations.download', [
        $this->workspace,
        $this->generation,
        'html',
    ]));

    $response->assertUnauthorized();
});

it('requires workspace membership to download', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $this->generation,
            'html',
        ]));

    $response->assertForbidden();
});

it('tracks download when successful', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('briefings/1/1/html.html', '<h1>Test</h1>');

    $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-generations.download', [
            $this->workspace,
            $this->generation,
            'html',
        ]))
        ->assertSuccessful();

    $this->assertDatabaseHas('briefing_downloads', [
        'briefing_generation_id' => $this->generation->id,
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'format' => 'html',
        'source' => 'dashboard',
    ]);
});
