<?php

declare(strict_types=1);

use App\Actions\Auth\LogoutUser;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('revokes the current access token', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $action = new LogoutUser;
    $action->handle($user);

    expect($user->tokens()->count())->toBe(0);
});
