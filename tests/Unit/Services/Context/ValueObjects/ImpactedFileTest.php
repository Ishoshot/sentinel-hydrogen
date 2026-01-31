<?php

declare(strict_types=1);

use App\Services\Context\ValueObjects\ImpactedFile;

it('can be constructed with all parameters', function (): void {
    $file = new ImpactedFile(
        filePath: 'src/Services/UserService.php',
        content: 'function getUserById($id) { ... }',
        matchedSymbol: 'getUserById',
        matchType: 'function_call',
        score: 0.85,
        matchCount: 3,
    );

    expect($file->filePath)->toBe('src/Services/UserService.php');
    expect($file->content)->toBe('function getUserById($id) { ... }');
    expect($file->matchedSymbol)->toBe('getUserById');
    expect($file->matchType)->toBe('function_call');
    expect($file->score)->toBe(0.85);
    expect($file->matchCount)->toBe(3);
});

it('creates from array with all fields', function (): void {
    $file = ImpactedFile::fromArray([
        'file_path' => 'app/Controller.php',
        'content' => 'class Controller { }',
        'matched_symbol' => 'Controller',
        'match_type' => 'class_instantiation',
        'score' => 0.9,
        'match_count' => 5,
    ]);

    expect($file->filePath)->toBe('app/Controller.php');
    expect($file->content)->toBe('class Controller { }');
    expect($file->matchedSymbol)->toBe('Controller');
    expect($file->matchType)->toBe('class_instantiation');
    expect($file->score)->toBe(0.9);
    expect($file->matchCount)->toBe(5);
});

it('creates from array with missing fields using defaults', function (): void {
    $file = ImpactedFile::fromArray([]);

    expect($file->filePath)->toBe('');
    expect($file->content)->toBe('');
    expect($file->matchedSymbol)->toBe('');
    expect($file->matchType)->toBe('unknown');
    expect($file->score)->toBe(0.0);
    expect($file->matchCount)->toBe(1);
});

it('handles non-numeric score values', function (): void {
    $file = ImpactedFile::fromArray([
        'score' => 'not-a-number',
    ]);

    expect($file->score)->toBe(0.0);
});

it('converts to array', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: 'test content',
        matchedSymbol: 'testFunction',
        matchType: 'function_call',
        score: 0.75,
        matchCount: 2,
    );

    $array = $file->toArray();

    expect($array['file_path'])->toBe('test.php');
    expect($array['content'])->toBe('test content');
    expect($array['matched_symbol'])->toBe('testFunction');
    expect($array['match_type'])->toBe('function_call');
    expect($array['score'])->toBe(0.75);
    expect($array['match_count'])->toBe(2);
    expect($array['reason'])->toBe('Calls function `testFunction()`');
});

it('generates reason for function call', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: '',
        matchedSymbol: 'myFunction',
        matchType: 'function_call',
        score: 0.5,
        matchCount: 1,
    );

    expect($file->getReason())->toBe('Calls function `myFunction()`');
});

it('generates reason for class instantiation', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: '',
        matchedSymbol: 'UserService',
        matchType: 'class_instantiation',
        score: 0.5,
        matchCount: 1,
    );

    expect($file->getReason())->toBe('Instantiates class `UserService`');
});

it('generates reason for method call', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: '',
        matchedSymbol: 'process',
        matchType: 'method_call',
        score: 0.5,
        matchCount: 1,
    );

    expect($file->getReason())->toBe('Calls method `process()`');
});

it('generates reason for extends', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: '',
        matchedSymbol: 'BaseClass',
        matchType: 'extends',
        score: 0.5,
        matchCount: 1,
    );

    expect($file->getReason())->toBe('Extends class `BaseClass`');
});

it('generates reason for implements', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: '',
        matchedSymbol: 'Repository',
        matchType: 'implements',
        score: 0.5,
        matchCount: 1,
    );

    expect($file->getReason())->toBe('Implements interface `Repository`');
});

it('generates default reason for unknown match type', function (): void {
    $file = new ImpactedFile(
        filePath: 'test.php',
        content: '',
        matchedSymbol: 'SomeSymbol',
        matchType: 'unknown_type',
        score: 0.5,
        matchCount: 1,
    );

    expect($file->getReason())->toBe('References `SomeSymbol`');
});
