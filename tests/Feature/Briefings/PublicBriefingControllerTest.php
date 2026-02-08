<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\Plan;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $this->briefing = Briefing::factory()->system()->create(['is_active' => true]);

    $this->generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    $this->share = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->create();
});

it('shows a shared briefing with valid token', function (): void {
    $response = $this->getJson(route('briefings.share.show', $this->share->token));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => ['id', 'status', 'narrative'],
        ]);
});

it('increments access count on each view', function (): void {
    expect($this->share->access_count)->toBe(0);

    $this->getJson(route('briefings.share.show', $this->share->token))
        ->assertSuccessful();

    expect($this->share->fresh()->access_count)->toBe(1);

    $this->getJson(route('briefings.share.show', $this->share->token))
        ->assertSuccessful();

    expect($this->share->fresh()->access_count)->toBe(2);
});

it('tracks download as share_link source', function (): void {
    $this->getJson(route('briefings.share.show', $this->share->token))
        ->assertSuccessful();

    $this->assertDatabaseHas('briefing_downloads', [
        'briefing_generation_id' => $this->generation->id,
        'source' => 'share_link',
        'format' => 'html',
    ]);
});

it('returns 404 for invalid token', function (): void {
    $response = $this->getJson(route('briefings.share.show', 'invalid-token-that-does-not-exist'));

    $response->assertNotFound()
        ->assertJson(['message' => 'This share link is invalid or has expired.']);
});

it('returns 404 for expired share', function (): void {
    $expiredShare = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->expired()
        ->create();

    $response = $this->getJson(route('briefings.share.show', $expiredShare->token));

    $response->assertNotFound();
});

it('returns 404 for revoked share', function (): void {
    $revokedShare = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->revoked()
        ->create();

    $response = $this->getJson(route('briefings.share.show', $revokedShare->token));

    $response->assertNotFound();
});

it('returns 403 when max accesses reached', function (): void {
    $exhaustedShare = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->exhausted()
        ->create();

    $response = $this->getJson(route('briefings.share.show', $exhaustedShare->token));

    $response->assertForbidden()
        ->assertJson(['message' => 'This share link has reached its maximum access limit.']);
});

it('requires password for password-protected shares', function (): void {
    $protectedShare = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->withPassword('secret123')
        ->create();

    $response = $this->getJson(route('briefings.share.show', $protectedShare->token));

    $response->assertUnauthorized()
        ->assertJson([
            'message' => 'This briefing is password protected.',
            'requires_password' => true,
        ]);
});

it('grants access with correct password', function (): void {
    $protectedShare = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->withPassword('secret123')
        ->create();

    $response = $this->getJson(
        route('briefings.share.show', $protectedShare->token).'?password=secret123'
    );

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['id', 'status']]);
});

it('rejects incorrect password', function (): void {
    $protectedShare = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->withPassword('secret123')
        ->create();

    $response = $this->getJson(
        route('briefings.share.show', $protectedShare->token).'?password=wrongpassword'
    );

    $response->assertUnauthorized()
        ->assertJson(['requires_password' => true]);
});

it('does not require authentication', function (): void {
    $response = $this->getJson(route('briefings.share.show', $this->share->token));

    $response->assertSuccessful();
});
