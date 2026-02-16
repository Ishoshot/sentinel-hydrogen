<?php

declare(strict_types=1);

use App\Services\GitHub\Policies\GitHubApiResponsePolicy;

beforeEach(function (): void {
    $this->policy = new GitHubApiResponsePolicy;
});

it('casts response to map', function (): void {
    $data = ['key' => 'value', 'nested' => ['a' => 1]];

    expect($this->policy->map($data))->toBe($data);
});

it('casts response to list of maps', function (): void {
    $data = [['id' => 1], ['id' => 2]];

    expect($this->policy->listOfMaps($data))->toBe($data);
});

it('casts response to map or string', function (): void {
    expect($this->policy->mapOrString(['key' => 'value']))->toBe(['key' => 'value']);
    expect($this->policy->mapOrString('string-response'))->toBe('string-response');
});

it('casts repository tree response', function (): void {
    $data = [
        'sha' => 'abc123',
        'url' => 'https://api.github.com/repos/owner/repo/git/trees/abc123',
        'tree' => [
            ['path' => 'src/app.php', 'mode' => '100644', 'type' => 'blob', 'sha' => 'def456'],
        ],
        'truncated' => false,
    ];

    expect($this->policy->repositoryTree($data))->toBe($data);
});
