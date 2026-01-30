<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\SensitiveDataFilter;

test('it redacts sensitive data across all context sections', function () {
    $githubToken = 'ghp_'.str_repeat('A', 36);

    $bag = new ContextBag(
        fileContents: [
            'app/Config.php' => "TOKEN={$githubToken}",
        ],
        guidelines: [
            [
                'path' => 'GUIDE.md',
                'description' => 'Security notes',
                'content' => "Do not expose {$githubToken}",
            ],
        ],
        repositoryContext: [
            'readme' => 'password=supersecret123',
            'contributing' => null,
        ],
        semantics: [
            'app/Config.php' => [
                'errors' => [
                    ['message' => "Found {$githubToken}"],
                ],
            ],
        ],
        projectContext: [
            'dependencies' => [
                [
                    'name' => "acme/{$githubToken}",
                    'version' => '1.0.0',
                ],
            ],
        ]
    );

    $filter = app(SensitiveDataFilter::class);
    $filter->filter($bag);

    $redactionPrefix = '[REDACTED:';

    expect($bag->fileContents['app/Config.php'])
        ->toContain($redactionPrefix)
        ->not->toContain($githubToken);

    expect($bag->guidelines[0]['content'])
        ->toContain($redactionPrefix)
        ->not->toContain($githubToken);

    expect($bag->repositoryContext['readme'])
        ->toContain('[REDACTED:password_config:pass***]')
        ->not->toContain('supersecret123');

    expect($bag->semantics['app/Config.php']['errors'][0]['message'])
        ->toContain($redactionPrefix);

    expect($bag->projectContext['dependencies'][0]['name'])
        ->toContain($redactionPrefix);
});
