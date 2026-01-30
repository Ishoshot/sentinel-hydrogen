<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

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

    $this->briefing = Briefing::factory()->system()->create([
        'is_active' => true,
    ]);
});

it('records briefing feedback in generation metadata', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->create([
            'generated_by_id' => $this->user->id,
            'status' => BriefingGenerationStatus::Completed,
        ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-generations.feedback', [$this->workspace, $generation]), [
            'rating' => 5,
            'comment' => 'Great summary.',
            'tags' => ['useful', 'clear'],
        ]);

    $response->assertOk();

    $generation->refresh();

    expect($generation->metadata['feedback'][0]['rating'])->toBe(5)
        ->and($generation->metadata['feedback'][0]['comment'])->toBe('Great summary.')
        ->and($generation->metadata['feedback'][0]['tags'])->toContain('useful');
});
