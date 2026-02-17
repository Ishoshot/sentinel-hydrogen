<?php

declare(strict_types=1);

use GrahamCampbell\GitHub\GitHubManager;

it('blocks real github http requests in tests', function (): void {
    $github = app(GitHubManager::class);

    expect(fn () => $github->connection()->repo()->show('owner', 'repository'))
        ->toThrow(RuntimeException::class, 'Blocked real GitHub HTTP request in test');
});
