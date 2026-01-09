<?php

declare(strict_types=1);

use App\Actions\Auth\HandleOAuthCallback;
use App\Enums\OAuthProvider;
use App\Models\ProviderIdentity;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

beforeEach(function (): void {
    $this->socialiteUser = Mockery::mock(SocialiteUser::class);
    $this->socialiteUser->shouldReceive('getId')->andReturn('12345');
    $this->socialiteUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $this->socialiteUser->shouldReceive('getName')->andReturn('Test User');
    $this->socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $this->socialiteUser->token = 'access-token';
    $this->socialiteUser->refreshToken = 'refresh-token';
});

it('creates a new user on first OAuth login', function (): void {
    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('test@example.com')
        ->and($user->name)->toBe('Test User')
        ->and($user->avatar_url)->toBe('https://example.com/avatar.jpg')
        ->and($user->password)->toBeNull();
});

it('creates a workspace for new user on first login', function (): void {
    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    expect($user->ownedWorkspaces)->toHaveCount(1);

    $workspace = $user->ownedWorkspaces->first();
    expect($workspace->name)->toBe("Test User's Workspace");
});

it('creates a team for new workspace on first login', function (): void {
    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    $workspace = $user->ownedWorkspaces->first();
    expect($workspace->team)->not->toBeNull()
        ->and($workspace->team->name)->toBe("Test User's Workspace");
});

it('creates an owner membership for new user', function (): void {
    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    $workspace = $user->ownedWorkspaces->first();
    $membership = $workspace->teamMembers()->where('user_id', $user->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role->value)->toBe('owner');
});

it('creates provider identity for new user', function (): void {
    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    $identity = ProviderIdentity::where('user_id', $user->id)
        ->where('provider', OAuthProvider::GitHub)
        ->first();

    expect($identity)->not->toBeNull()
        ->and($identity->provider_user_id)->toBe('12345');
});

it('links provider to existing user with same email', function (): void {
    $existingUser = User::factory()->create(['email' => 'test@example.com']);

    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    expect($user->id)->toBe($existingUser->id)
        ->and($user->providerIdentities)->toHaveCount(1);
});

it('returns existing user when already linked to provider', function (): void {
    $existingUser = User::factory()->create(['email' => 'test@example.com']);
    ProviderIdentity::factory()->create([
        'user_id' => $existingUser->id,
        'provider' => OAuthProvider::GitHub,
        'provider_user_id' => '12345',
    ]);

    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    expect($user->id)->toBe($existingUser->id)
        ->and(ProviderIdentity::where('user_id', $existingUser->id)->count())->toBe(1);
});

it('updates provider identity tokens on subsequent login', function (): void {
    $existingUser = User::factory()->create(['email' => 'test@example.com']);
    $identity = ProviderIdentity::factory()->create([
        'user_id' => $existingUser->id,
        'provider' => OAuthProvider::GitHub,
        'provider_user_id' => '12345',
        'access_token' => 'old-token',
    ]);

    $action = app(HandleOAuthCallback::class);

    $action->execute(OAuthProvider::GitHub, $this->socialiteUser);

    $identity->refresh();
    expect($identity->access_token)->toBe('access-token');
});

it('supports multiple providers for same user', function (): void {
    $googleSocialiteUser = Mockery::mock(SocialiteUser::class);
    $googleSocialiteUser->shouldReceive('getId')->andReturn('google-12345');
    $googleSocialiteUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $googleSocialiteUser->shouldReceive('getName')->andReturn('Test User');
    $googleSocialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $googleSocialiteUser->token = 'google-access-token';
    $googleSocialiteUser->refreshToken = 'google-refresh-token';

    $action = app(HandleOAuthCallback::class);

    $user = $action->execute(OAuthProvider::GitHub, $this->socialiteUser);
    $sameUser = $action->execute(OAuthProvider::Google, $googleSocialiteUser);

    expect($sameUser->id)->toBe($user->id)
        ->and($user->providerIdentities()->count())->toBe(2);
});
