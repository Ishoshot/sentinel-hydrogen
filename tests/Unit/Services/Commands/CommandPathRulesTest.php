<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Commands\CommandPathRules;
use App\Services\Context\SensitiveDataRedactor;
use App\Support\PathRuleMatcher;

it('respects ignore patterns', function (): void {
    $paths = new PathsConfig(ignore: ['app/**']);
    $rules = new CommandPathRules($paths, new SensitiveDataRedactor(), new PathRuleMatcher());

    expect($rules->shouldIncludePath('app/Models/User.php'))->toBeFalse()
        ->and($rules->shouldIncludePath('resources/views/app.blade.php'))->toBeTrue();
});

it('respects include patterns', function (): void {
    $paths = new PathsConfig(include: ['app/**']);
    $rules = new CommandPathRules($paths, new SensitiveDataRedactor(), new PathRuleMatcher());

    expect($rules->shouldIncludePath('app/Models/User.php'))->toBeTrue()
        ->and($rules->shouldIncludePath('resources/views/app.blade.php'))->toBeFalse();
});

it('flags sensitive paths from config and built-in rules', function (): void {
    $paths = new PathsConfig(sensitive: ['**/secrets.yml']);
    $rules = new CommandPathRules($paths, new SensitiveDataRedactor(), new PathRuleMatcher());

    expect($rules->isSensitivePath('config/secrets.yml'))->toBeTrue()
        ->and($rules->isSensitivePath('.env'))->toBeTrue()
        ->and($rules->isSensitivePath('app/Models/User.php'))->toBeFalse();
});

it('redacts content for sensitive paths', function (): void {
    $paths = new PathsConfig(sensitive: ['**/.env']);
    $rules = new CommandPathRules($paths, new SensitiveDataRedactor(), new PathRuleMatcher());

    expect($rules->sanitizeContentForPath('.env', 'DB_PASSWORD=secret'))->toBe('[REDACTED - sensitive file]');
});

it('redacts sensitive patterns in content', function (): void {
    $rules = new CommandPathRules(new PathsConfig(), new SensitiveDataRedactor(), new PathRuleMatcher());

    $redacted = $rules->redact('api_key=abcdef123456789012345');

    expect($redacted)->toContain('[REDACTED:api_key:');
});
