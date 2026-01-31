<?php

declare(strict_types=1);

use App\Enums\Commands\CommandType;
use App\Services\Commands\ValueObjects\ContextHints;
use App\Services\Commands\ValueObjects\ParsedCommand;

it('can be constructed with all parameters', function (): void {
    $hints = ContextHints::empty();
    $command = new ParsedCommand(
        found: true,
        commandType: CommandType::Explain,
        query: 'explain this code',
        contextHints: $hints,
    );

    expect($command->found)->toBeTrue();
    expect($command->commandType)->toBe(CommandType::Explain);
    expect($command->query)->toBe('explain this code');
    expect($command->contextHints)->toBe($hints);
});

it('can be constructed with minimal parameters', function (): void {
    $command = new ParsedCommand(found: false);

    expect($command->found)->toBeFalse();
    expect($command->commandType)->toBeNull();
    expect($command->query)->toBeNull();
    expect($command->contextHints)->toBeNull();
});

it('creates from array', function (): void {
    $command = ParsedCommand::fromArray([
        'found' => true,
        'command_type' => CommandType::Review,
        'query' => 'review src/app.php',
        'context_hints' => [
            'files' => ['src/app.php'],
            'symbols' => [],
            'lines' => [],
        ],
    ]);

    expect($command->found)->toBeTrue();
    expect($command->commandType)->toBe(CommandType::Review);
    expect($command->query)->toBe('review src/app.php');
    expect($command->contextHints)->not->toBeNull();
    expect($command->contextHints->files)->toBe(['src/app.php']);
});

it('creates not found result', function (): void {
    $command = ParsedCommand::notFound();

    expect($command->found)->toBeFalse();
    expect($command->commandType)->toBeNull();
    expect($command->query)->toBeNull();
    expect($command->contextHints)->toBeNull();
});

it('creates found result with command details', function (): void {
    $hints = new ContextHints(files: ['test.php']);
    $command = ParsedCommand::found(
        commandType: CommandType::Analyze,
        query: 'analyze this function',
        contextHints: $hints,
    );

    expect($command->found)->toBeTrue();
    expect($command->commandType)->toBe(CommandType::Analyze);
    expect($command->query)->toBe('analyze this function');
    expect($command->contextHints)->toBe($hints);
});

it('checks if command was found when true', function (): void {
    $command = ParsedCommand::found(
        commandType: CommandType::Find,
        query: 'find usages',
        contextHints: ContextHints::empty(),
    );

    expect($command->wasFound())->toBeTrue();
});

it('checks if command was found when false', function (): void {
    $command = ParsedCommand::notFound();

    expect($command->wasFound())->toBeFalse();
});

it('converts found command to array', function (): void {
    $hints = new ContextHints(
        files: ['file.php'],
        symbols: ['MyClass'],
        lines: [],
    );
    $command = ParsedCommand::found(
        commandType: CommandType::Summarize,
        query: 'summarize PR',
        contextHints: $hints,
    );

    $array = $command->toArray();

    expect($array)->toBe([
        'found' => true,
        'command_type' => CommandType::Summarize,
        'query' => 'summarize PR',
        'context_hints' => [
            'files' => ['file.php'],
            'symbols' => ['MyClass'],
            'lines' => [],
        ],
    ]);
});

it('returns null when converting not found command to array', function (): void {
    $command = ParsedCommand::notFound();

    expect($command->toArray())->toBeNull();
});

it('handles all command types', function (CommandType $type): void {
    $command = ParsedCommand::found(
        commandType: $type,
        query: 'test query',
        contextHints: ContextHints::empty(),
    );

    expect($command->commandType)->toBe($type);
})->with([
    'explain' => CommandType::Explain,
    'analyze' => CommandType::Analyze,
    'review' => CommandType::Review,
    'summarize' => CommandType::Summarize,
    'find' => CommandType::Find,
]);
