<?php

declare(strict_types=1);

use App\Services\Reviews\ValueObjects\GitHubUser;

it('can be constructed with login and avatar url', function (): void {
    $user = new GitHubUser(
        login: 'testuser',
        avatarUrl: 'https://github.com/testuser.png',
    );

    expect($user->login)->toBe('testuser');
    expect($user->avatarUrl)->toBe('https://github.com/testuser.png');
});

it('can be constructed with null avatar url', function (): void {
    $user = new GitHubUser(
        login: 'testuser',
    );

    expect($user->login)->toBe('testuser');
    expect($user->avatarUrl)->toBeNull();
});

it('can be created from array with avatar url', function (): void {
    $user = GitHubUser::fromArray([
        'login' => 'johndoe',
        'avatar_url' => 'https://github.com/johndoe.png',
    ]);

    expect($user->login)->toBe('johndoe');
    expect($user->avatarUrl)->toBe('https://github.com/johndoe.png');
});

it('can be created from array without avatar url', function (): void {
    $user = GitHubUser::fromArray([
        'login' => 'johndoe',
        'avatar_url' => null,
    ]);

    expect($user->login)->toBe('johndoe');
    expect($user->avatarUrl)->toBeNull();
});

it('converts to array correctly', function (): void {
    $user = new GitHubUser(
        login: 'testuser',
        avatarUrl: 'https://github.com/testuser.png',
    );

    $array = $user->toArray();

    expect($array)->toBe([
        'login' => 'testuser',
        'avatar_url' => 'https://github.com/testuser.png',
    ]);
});

it('converts to array with null avatar', function (): void {
    $user = new GitHubUser(
        login: 'testuser',
    );

    $array = $user->toArray();

    expect($array)->toBe([
        'login' => 'testuser',
        'avatar_url' => null,
    ]);
});

it('roundtrips through fromArray and toArray', function (): void {
    $original = [
        'login' => 'developer',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
    ];

    $user = GitHubUser::fromArray($original);
    $result = $user->toArray();

    expect($result)->toBe($original);
});
