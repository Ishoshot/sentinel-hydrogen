<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\ImpactSearchPatternFactory;

it('builds function call patterns', function (): void {
    $factory = new ImpactSearchPatternFactory;

    $patterns = $factory->build([
        'name' => 'calculateTotal',
        'type' => 'function',
        'file' => 'app/helpers.php',
    ]);

    expect($patterns)->toBe([
        'calculateTotal(' => 'function_call',
    ]);
});

it('builds class instantiation and inheritance patterns', function (): void {
    $factory = new ImpactSearchPatternFactory;

    $patterns = $factory->build([
        'name' => 'UserService',
        'type' => 'class',
        'file' => 'app/Services/UserService.php',
    ]);

    expect($patterns)->toBe([
        'new UserService' => 'class_instantiation',
        'extends UserService' => 'extends',
        'implements UserService' => 'implements',
    ]);
});

it('builds method call patterns for instance and static calls', function (): void {
    $factory = new ImpactSearchPatternFactory;

    $patterns = $factory->build([
        'name' => 'findById',
        'type' => 'method',
        'file' => 'app/Repositories/UserRepository.php',
    ]);

    expect($patterns)->toBe([
        '->findById(' => 'method_call',
        '::findById(' => 'method_call',
    ]);
});

it('builds generic reference pattern for unknown types', function (): void {
    $factory = new ImpactSearchPatternFactory;

    $patterns = $factory->build([
        'name' => 'MAX_RETRIES',
        'type' => 'constant',
        'file' => 'app/Constants.php',
    ]);

    expect($patterns)->toBe([
        'MAX_RETRIES' => 'reference',
    ]);
});

it('builds generic reference pattern for empty type', function (): void {
    $factory = new ImpactSearchPatternFactory;

    $patterns = $factory->build([
        'name' => 'someVariable',
        'type' => '',
        'file' => 'app/Service.php',
    ]);

    expect($patterns)->toBe([
        'someVariable' => 'reference',
    ]);
});

it('handles single character names', function (): void {
    $factory = new ImpactSearchPatternFactory;

    $patterns = $factory->build([
        'name' => 'x',
        'type' => 'function',
        'file' => 'app/helpers.php',
    ]);

    expect($patterns)->toBe([
        'x(' => 'function_call',
    ]);
});
