<?php

declare(strict_types=1);

use App\Actions\Users\MarkGettingStartedAsSeen;
use App\Models\User;

it('marks the getting started guide as seen', function (): void {
    $user = User::factory()->create(['has_seen_getting_started' => false]);

    $action = new MarkGettingStartedAsSeen;
    $result = $action->handle($user);

    expect($result->has_seen_getting_started)->toBeTrue();
    expect($user->fresh()->has_seen_getting_started)->toBeTrue();
});
