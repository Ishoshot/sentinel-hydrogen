<?php

declare(strict_types=1);

use App\Actions\GitHub\ResolveInstallationConnection;
use App\Exceptions\GitHub\InvalidInstallationStateException;
use App\Models\Connection;
use App\Models\Installation;

beforeEach(function (): void {
    $this->action = new ResolveInstallationConnection();
});

describe('handle', function (): void {
    it('returns connection from existing installation when state is null', function (): void {
        $connection = Connection::factory()->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345678,
        ]);

        $result = $this->action->handle(12345678, null);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($connection->id);
    });

    it('returns null when no installation exists and state is null', function (): void {
        $result = $this->action->handle(99999999, null);

        expect($result)->toBeNull();
    });

    it('resolves connection from state for pending connections', function (): void {
        $stateValue = 'valid-state-token-abc123';

        $connection = Connection::factory()->pending()->create([
            'metadata' => ['state' => $stateValue],
        ]);

        $result = $this->action->handle(12345678, $stateValue);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($connection->id);
    });

    it('throws exception when state does not match any pending connection', function (): void {
        Connection::factory()->pending()->create([
            'metadata' => ['state' => 'different-state'],
        ]);

        $this->action->handle(12345678, 'non-matching-state');
    })->throws(InvalidInstallationStateException::class, 'Invalid or expired state parameter.');

    it('throws exception when no pending connections exist', function (): void {
        $this->action->handle(12345678, 'some-state-value');
    })->throws(InvalidInstallationStateException::class);

    it('ignores expired pending connections older than 15 minutes', function (): void {
        $connection = Connection::factory()->pending()->create([
            'metadata' => ['state' => 'expired-state-token'],
            'created_at' => now()->subMinutes(20),
        ]);

        $this->action->handle(12345678, 'expired-state-token');
    })->throws(InvalidInstallationStateException::class);

    it('ignores active connections when resolving from state', function (): void {
        Connection::factory()->active()->create([
            'metadata' => ['state' => 'active-state-token'],
        ]);

        $this->action->handle(12345678, 'active-state-token');
    })->throws(InvalidInstallationStateException::class);

    it('resolves the correct connection when multiple pending connections exist', function (): void {
        $targetState = 'target-state-value';

        Connection::factory()->pending()->create([
            'metadata' => ['state' => 'other-state-1'],
        ]);

        $targetConnection = Connection::factory()->pending()->create([
            'metadata' => ['state' => $targetState],
        ]);

        Connection::factory()->pending()->create([
            'metadata' => ['state' => 'other-state-2'],
        ]);

        $result = $this->action->handle(12345678, $targetState);

        expect($result->id)->toBe($targetConnection->id);
    });

    it('handles connection with null metadata when resolving from state', function (): void {
        Connection::factory()->pending()->create([
            'metadata' => null,
        ]);

        $this->action->handle(12345678, 'some-state');
    })->throws(InvalidInstallationStateException::class);
});
