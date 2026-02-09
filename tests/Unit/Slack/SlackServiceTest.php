<?php

declare(strict_types=1);

use App\Models\SlackIntegration;
use App\Services\Slack\SlackService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new SlackService;

    $this->integration = new SlackIntegration;
    $this->integration->access_token = 'xoxb-test-token';
});

it('exchanges code for token successfully', function (): void {
    Http::fake([
        'slack.com/api/oauth.v2.access' => Http::response([
            'ok' => true,
            'access_token' => 'xoxb-test-token',
            'bot_user_id' => 'U123BOT',
            'team' => ['id' => 'T123', 'name' => 'Test Team'],
            'scope' => 'chat:write,channels:read',
            'authed_user' => ['id' => 'U456USER'],
        ]),
    ]);

    $result = $this->service->exchangeCodeForToken('test-code', 'https://example.com/callback');

    expect($result)
        ->access_token->toBe('xoxb-test-token')
        ->bot_user_id->toBe('U123BOT')
        ->team->id->toBe('T123')
        ->team->name->toBe('Test Team')
        ->scope->toBe('chat:write,channels:read')
        ->authed_user->id->toBe('U456USER');
});

it('throws exception when token exchange fails', function (): void {
    Http::fake([
        'slack.com/api/oauth.v2.access' => Http::response([
            'ok' => false,
            'error' => 'invalid_code',
        ]),
    ]);

    $this->service->exchangeCodeForToken('bad-code', 'https://example.com/callback');
})->throws(RuntimeException::class, 'Slack OAuth failed: invalid_code');

it('sends message via chat.postMessage', function (): void {
    Http::fake([
        'slack.com/api/chat.postMessage' => Http::response([
            'ok' => true,
            'channel' => 'C001',
            'ts' => '1234567890.123456',
        ]),
    ]);

    $this->service->sendMessage($this->integration, 'C001', 'Hello');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'chat.postMessage')
            && $request->data()['channel'] === 'C001'
            && $request->data()['text'] === 'Hello'
            && ! isset($request->data()['blocks']);
    });
});

it('sends message with blocks', function (): void {
    Http::fake([
        'slack.com/api/chat.postMessage' => Http::response([
            'ok' => true,
        ]),
    ]);

    $blocks = [
        [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => 'Test block content',
            ],
        ],
    ];

    $this->service->sendMessage($this->integration, 'C001', 'Fallback', $blocks);

    Http::assertSent(function ($request) {
        return $request->data()['text'] === 'Fallback'
            && isset($request->data()['blocks']);
    });
});

it('throws exception when message delivery fails', function (): void {
    Http::fake([
        'slack.com/api/chat.postMessage' => Http::response([
            'ok' => false,
            'error' => 'channel_not_found',
        ], 400),
    ]);

    $this->service->sendMessage($this->integration, 'C999', 'Hello');
})->throws(Illuminate\Http\Client\RequestException::class);

it('lists channels successfully', function (): void {
    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => true,
            'channels' => [
                ['id' => 'C001', 'name' => 'general', 'is_member' => true, 'num_members' => 10],
                ['id' => 'C002', 'name' => 'random', 'is_member' => false, 'num_members' => 8],
            ],
        ]),
    ]);

    $channels = $this->service->listChannels($this->integration);

    expect($channels)
        ->toHaveCount(2)
        ->sequence(
            fn ($channel) => $channel->id->toBe('C001')
                ->name->toBe('general')
                ->is_member->toBeTrue(),
            fn ($channel) => $channel->id->toBe('C002'),
        );
});

it('test connection returns true on success', function (): void {
    Http::fake([
        'slack.com/api/auth.test' => Http::response([
            'ok' => true,
            'user_id' => 'U123',
            'team' => 'Test Team',
        ]),
    ]);

    expect($this->service->testConnection($this->integration))->toBeTrue();
});

it('test connection returns false on failure', function (): void {
    Http::fake([
        'slack.com/api/auth.test' => Http::response([
            'ok' => false,
            'error' => 'invalid_auth',
        ]),
    ]);

    expect($this->service->testConnection($this->integration))->toBeFalse();
});

it('revokes token successfully', function (): void {
    Http::fake([
        'slack.com/api/auth.revoke' => Http::response([
            'ok' => true,
            'revoked' => true,
        ]),
    ]);

    expect($this->service->revokeToken($this->integration))->toBeTrue();
});

it('revoke token returns false on failure', function (): void {
    Http::fake([
        'slack.com/api/auth.revoke' => Http::response([
            'ok' => false,
            'error' => 'token_already_revoked',
        ]),
    ]);

    expect($this->service->revokeToken($this->integration))->toBeFalse();
});
