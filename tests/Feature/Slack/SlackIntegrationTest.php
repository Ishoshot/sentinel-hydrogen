<?php

declare(strict_types=1);

use App\Models\SlackIntegration;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Slack\Contracts\SlackServiceContract;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
    ]);

    $this->workspace->teamMembers()->create([
        'user_id' => $this->user->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->member = User::factory()->create();
    $this->workspace->teamMembers()->create([
        'user_id' => $this->member->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);
});

it('shows null when no slack integration exists', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('slack.integration.show', $this->workspace));

    $response->assertOk()
        ->assertJsonPath('data', null);
});

it('shows slack integration status when connected', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create([
            'channel_name' => '#engineering',
            'channel_id' => 'C12345',
            'team_name' => 'My Team',
            'slack_team_id' => 'T12345',
        ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('slack.integration.show', $this->workspace));

    $response->assertOk()
        ->assertJsonPath('data.workspace_id', $this->workspace->id)
        ->assertJsonPath('data.channel_name', '#engineering')
        ->assertJsonPath('data.channel_id', 'C12345')
        ->assertJsonPath('data.team_name', 'My Team')
        ->assertJsonPath('data.slack_team_id', 'T12345')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_connected', true)
        ->assertJsonPath('data.has_channel', true)
        ->assertJsonPath('data.is_fully_configured', true)
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonMissingPath('data.state');
});

it('initiates oauth and returns oauth url', function (): void {
    config([
        'slack.client_id' => 'test-client-id',
        'slack.redirect_uri' => 'http://sentinel.test/api/slack/callback',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('slack.integration.connect', $this->workspace));

    $response->assertOk()
        ->assertJsonStructure(['data', 'oauth_url']);

    $oauthUrl = $response->json('oauth_url');
    expect($oauthUrl)
        ->toContain('https://slack.com/oauth/v2/authorize')
        ->toContain('client_id=')
        ->toContain('state=');

    \Pest\Laravel\assertDatabaseHas('slack_integrations', [
        'workspace_id' => $this->workspace->id,
        'is_active' => false,
    ]);

    $integration = SlackIntegration::where('workspace_id', $this->workspace->id)->first();
    expect($integration)
        ->state->not->toBeNull()
        ->state_expires_at->not->toBeNull()
        ->state_expires_at->isFuture()->toBeTrue();
});

it('handles oauth callback successfully', function (): void {
    config(['slack.redirect_uri' => 'http://sentinel.test/api/slack/callback']);

    $integration = SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->pending()
        ->create([
            'state' => 'valid-state-token-1234567890123456789012',
            'state_expires_at' => now()->addMinutes(15),
        ]);

    $mockSlackService = Mockery::mock(SlackServiceContract::class);
    $mockSlackService->shouldReceive('exchangeCodeForToken')
        ->once()
        ->with('oauth-code-123', 'http://sentinel.test/api/slack/callback')
        ->andReturn([
            'access_token' => 'xoxb-test-token',
            'bot_user_id' => 'U123BOT',
            'team' => ['id' => 'T123TEAM', 'name' => 'Test Team'],
            'scope' => 'chat:write,channels:read',
            'authed_user' => ['id' => 'U123USER'],
        ]);

    $this->app->instance(SlackServiceContract::class, $mockSlackService);

    $response = $this->get(route('slack.callback', [
        'code' => 'oauth-code-123',
        'state' => 'valid-state-token-1234567890123456789012',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/settings/integrations?slack=connected');

    $integration->refresh();
    expect($integration)
        ->is_active->toBeTrue()
        ->bot_user_id->toBe('U123BOT')
        ->slack_team_id->toBe('T123TEAM')
        ->team_name->toBe('Test Team')
        ->state->toBeNull();
});

it('rejects callback with invalid state', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->pending()
        ->create([
            'state' => 'correct-state-token-12345678901234567890',
            'state_expires_at' => now()->addMinutes(15),
        ]);

    $response = $this->get(route('slack.callback', [
        'code' => 'oauth-code-123',
        'state' => 'wrong-state-token-123456789012345678901',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('/auth/error?message=')
        ->toContain('Invalid+or+expired+Slack+OAuth+state.');
});

it('rejects callback with expired state', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->pending()
        ->create([
            'state' => 'expired-state-token-1234567890123456789',
            'state_expires_at' => now()->subMinute(),
        ]);

    $response = $this->get(route('slack.callback', [
        'code' => 'oauth-code-123',
        'state' => 'expired-state-token-1234567890123456789',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('/auth/error?message=')
        ->toContain('Invalid+or+expired+Slack+OAuth+state.');
});

it('handles callback when user declines authorization', function (): void {
    $response = $this->get(route('slack.callback', [
        'error' => 'access_denied',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('/auth/error?message=')
        ->toContain('Slack+authorization+was+declined.');
});

it('lists channels for a connected integration', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create();

    $mockSlackService = Mockery::mock(SlackServiceContract::class);
    $mockSlackService->shouldReceive('listChannels')
        ->once()
        ->andReturn([
            ['id' => 'C001', 'name' => 'general', 'is_member' => true, 'num_members' => 10],
            ['id' => 'C002', 'name' => 'engineering', 'is_member' => false, 'num_members' => 5],
        ]);

    $this->app->instance(SlackServiceContract::class, $mockSlackService);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('slack.integration.channels', $this->workspace));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'C001')
        ->assertJsonPath('data.0.name', 'general')
        ->assertJsonPath('data.1.id', 'C002');
});

it('updates selected channel', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->withoutChannel()
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('slack.integration.update-channel', $this->workspace), [
            'channel_id' => 'C001',
            'channel_name' => '#engineering',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.channel_id', 'C001')
        ->assertJsonPath('data.channel_name', '#engineering')
        ->assertJsonPath('data.has_channel', true)
        ->assertJsonPath('data.is_fully_configured', true);
});

it('validates channel update requires channel_id and channel_name', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('slack.integration.update-channel', $this->workspace), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['channel_id', 'channel_name']);
});

it('disconnects slack with token revocation', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create();

    $mockSlackService = Mockery::mock(SlackServiceContract::class);
    $mockSlackService->shouldReceive('revokeToken')
        ->once()
        ->andReturn(true);

    $this->app->instance(SlackServiceContract::class, $mockSlackService);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson(route('slack.integration.destroy', $this->workspace));

    $response->assertOk()
        ->assertJsonPath('message', 'Slack disconnected successfully.');

    \Pest\Laravel\assertDatabaseMissing('slack_integrations', [
        'workspace_id' => $this->workspace->id,
    ]);
});

it('returns 404 when disconnecting without integration', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson(route('slack.integration.destroy', $this->workspace));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->getJson(route('slack.integration.show', $this->workspace));

    $response->assertUnauthorized();
});

it('requires workspace membership', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson(route('slack.integration.show', $this->workspace));

    $response->assertForbidden();
});

it('only owner or admin can connect slack', function (): void {
    $response = $this->actingAs($this->member, 'sanctum')
        ->postJson(route('slack.integration.connect', $this->workspace));

    $response->assertForbidden();
});

it('only owner or admin can disconnect slack', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create();

    $response = $this->actingAs($this->member, 'sanctum')
        ->deleteJson(route('slack.integration.destroy', $this->workspace));

    $response->assertForbidden();
});

it('only owner or admin can update channel', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create();

    $response = $this->actingAs($this->member, 'sanctum')
        ->patchJson(route('slack.integration.update-channel', $this->workspace), [
            'channel_id' => 'C001',
            'channel_name' => '#engineering',
        ]);

    $response->assertForbidden();
});
