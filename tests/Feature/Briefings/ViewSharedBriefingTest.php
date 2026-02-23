<?php

declare(strict_types=1);

use App\Actions\Briefings\ViewSharedBriefing;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $this->briefing = Briefing::factory()->system()->create(['is_active' => true]);

    $this->generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    $this->action = app(ViewSharedBriefing::class);
    $this->request = Request::create('/test');
});

it('logs a warning when share token is invalid or expired', function (): void {
    Log::spy();

    $result = $this->action->handle('nonexistent-token', null, $this->request);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->httpStatus)->toBe(404);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'invalid or expired token'))
        ->once();
});

it('logs a warning when max accesses are reached', function (): void {
    Log::spy();

    $share = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->exhausted()
        ->create();

    $result = $this->action->handle($share->token, null, $this->request);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->httpStatus)->toBe(403);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'max accesses reached'))
        ->once();
});

it('logs a warning when password is incorrect', function (): void {
    Log::spy();

    $share = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->withPassword('correct-password')
        ->create();

    $result = $this->action->handle($share->token, 'wrong-password', $this->request);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->httpStatus)->toBe(401);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'incorrect password'))
        ->once();
});

it('does not log a warning on successful access', function (): void {
    Log::spy();

    $share = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->create();

    $result = $this->action->handle($share->token, null, $this->request);

    expect($result->isSuccessful())->toBeTrue();

    Log::shouldNotHaveReceived('warning');
});
