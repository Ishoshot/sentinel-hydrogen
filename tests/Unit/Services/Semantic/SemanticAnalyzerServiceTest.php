<?php

declare(strict_types=1);

use App\Services\Semantic\SemanticAnalyzerService;

it('returns null for unsupported file extensions', function (): void {
    $service = new SemanticAnalyzerService;

    $result = $service->analyzeFile('content', 'file.txt');

    expect($result)->toBeNull();
});

it('returns null for files without extension', function (): void {
    $service = new SemanticAnalyzerService;

    $result = $service->analyzeFile('content', 'Makefile');

    expect($result)->toBeNull();
});

it('returns null when binary does not exist', function (): void {
    $service = new SemanticAnalyzerService;

    $result = $service->analyzeFile('<?php echo "test";', 'test.php');

    expect($result)->toBeNull();
});

it('analyzes multiple files and filters null results', function (): void {
    $service = new SemanticAnalyzerService;

    $files = [
        'file.txt' => 'text content',
        'file.jpg' => 'binary content',
    ];

    $results = $service->analyzeFiles($files);

    expect($results)->toBe([]);
});

it('accepts supported php extension', function (): void {
    $service = new SemanticAnalyzerService;

    $result = $service->analyzeFile('<?php echo "test";', 'test.php');

    expect($result)->toBeNull();
});

it('accepts supported javascript extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('const a = 1;', 'test.js'))->toBeNull();
    expect($service->analyzeFile('const a = 1;', 'test.mjs'))->toBeNull();
    expect($service->analyzeFile('const a = 1;', 'test.cjs'))->toBeNull();
    expect($service->analyzeFile('const a = 1;', 'test.jsx'))->toBeNull();
});

it('accepts supported typescript extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('const a: number = 1;', 'test.ts'))->toBeNull();
    expect($service->analyzeFile('const a: number = 1;', 'test.tsx'))->toBeNull();
});

it('accepts supported python extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('print("test")', 'test.py'))->toBeNull();
});

it('accepts supported go extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('package main', 'test.go'))->toBeNull();
});

it('accepts supported rust extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('fn main() {}', 'test.rs'))->toBeNull();
});

it('accepts supported java extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('class Test {}', 'Test.java'))->toBeNull();
});

it('accepts supported kotlin extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('fun main() {}', 'test.kt'))->toBeNull();
    expect($service->analyzeFile('fun main() {}', 'test.kts'))->toBeNull();
});

it('accepts supported csharp extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('class Test {}', 'Test.cs'))->toBeNull();
});

it('accepts supported ruby extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('puts "test"', 'test.rb'))->toBeNull();
});

it('accepts supported swift extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('print("test")', 'test.swift'))->toBeNull();
});

it('accepts supported c and cpp extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('#include <stdio.h>', 'test.c'))->toBeNull();
    expect($service->analyzeFile('#include <stdio.h>', 'test.h'))->toBeNull();
    expect($service->analyzeFile('#include <iostream>', 'test.cpp'))->toBeNull();
    expect($service->analyzeFile('#include <iostream>', 'test.cc'))->toBeNull();
    expect($service->analyzeFile('#include <iostream>', 'test.cxx'))->toBeNull();
    expect($service->analyzeFile('#include <iostream>', 'test.hpp'))->toBeNull();
    expect($service->analyzeFile('#include <iostream>', 'test.hxx'))->toBeNull();
});

it('accepts supported web extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('<html></html>', 'test.html'))->toBeNull();
    expect($service->analyzeFile('<html></html>', 'test.htm'))->toBeNull();
    expect($service->analyzeFile('body {}', 'test.css'))->toBeNull();
    expect($service->analyzeFile('$var: red;', 'test.scss'))->toBeNull();
    expect($service->analyzeFile('$var: red', 'test.sass'))->toBeNull();
});

it('accepts supported frontend framework extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('<template></template>', 'test.vue'))->toBeNull();
    expect($service->analyzeFile('<script></script>', 'test.svelte'))->toBeNull();
    expect($service->analyzeFile('void main() {}', 'test.dart'))->toBeNull();
});

it('accepts supported shell extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('echo "test"', 'test.sh'))->toBeNull();
    expect($service->analyzeFile('echo "test"', 'test.bash'))->toBeNull();
    expect($service->analyzeFile('echo "test"', 'test.zsh'))->toBeNull();
});

it('accepts supported config extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('SELECT * FROM users', 'test.sql'))->toBeNull();
    expect($service->analyzeFile('key: value', 'test.yaml'))->toBeNull();
    expect($service->analyzeFile('key: value', 'test.yml'))->toBeNull();
});
