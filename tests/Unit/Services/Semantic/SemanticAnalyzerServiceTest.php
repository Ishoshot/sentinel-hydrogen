<?php

declare(strict_types=1);

use App\Services\Semantic\SemanticAnalyzerService;

beforeEach(function (): void {
    $this->service = new SemanticAnalyzerService();

    // Ensure binary exists (should be built by composer or CI)
    $binaryPath = base_path('bin/semantic-analyzer');
    if (! file_exists($binaryPath)) {
        $this->markTestSkipped('Semantic analyzer binary not found. Run: composer build-analyzer');
    }
});

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

    expect($result)->toBeArray();
});

it('accepts supported javascript extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('const a = 1;', 'test.js'))->toBeArray();
    expect($service->analyzeFile('const a = 1;', 'test.mjs'))->toBeArray();
    expect($service->analyzeFile('const a = 1;', 'test.cjs'))->toBeArray();
    expect($service->analyzeFile('const a = 1;', 'test.jsx'))->toBeArray();
});

it('accepts supported typescript extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('const a: number = 1;', 'test.ts'))->toBeArray();
    expect($service->analyzeFile('const a: number = 1;', 'test.tsx'))->toBeArray();
});

it('accepts supported python extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('print("test")', 'test.py'))->toBeArray();
});

it('accepts supported go extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('package main', 'test.go'))->toBeArray();
});

it('accepts supported rust extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('fn main() {}', 'test.rs'))->toBeArray();
});

it('accepts supported java extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('class Test {}', 'Test.java'))->toBeArray();
});

it('accepts supported kotlin extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('fun main() {}', 'test.kt'))->toBeArray();
    expect($service->analyzeFile('fun main() {}', 'test.kts'))->toBeArray();
});

it('accepts supported csharp extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('class Test {}', 'Test.cs'))->toBeArray();
});

it('accepts supported ruby extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('puts "test"', 'test.rb'))->toBeArray();
});

it('accepts supported swift extension', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('print("test")', 'test.swift'))->toBeArray();
});

it('accepts supported c and cpp extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('#include <stdio.h>', 'test.c'))->toBeArray();
    expect($service->analyzeFile('#include <stdio.h>', 'test.h'))->toBeArray();
    expect($service->analyzeFile('#include <iostream>', 'test.cpp'))->toBeArray();
    expect($service->analyzeFile('#include <iostream>', 'test.cc'))->toBeArray();
    expect($service->analyzeFile('#include <iostream>', 'test.cxx'))->toBeArray();
    expect($service->analyzeFile('#include <iostream>', 'test.hpp'))->toBeArray();
    expect($service->analyzeFile('#include <iostream>', 'test.hxx'))->toBeArray();
});

it('accepts supported web extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('<html></html>', 'test.html'))->toBeArray();
    expect($service->analyzeFile('<html></html>', 'test.htm'))->toBeArray();
    expect($service->analyzeFile('body {}', 'test.css'))->toBeArray();
    expect($service->analyzeFile('$var: red;', 'test.scss'))->toBeArray();
    expect($service->analyzeFile('$var: red', 'test.sass'))->toBeArray();
});

it('accepts supported frontend framework extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('<template></template>', 'test.vue'))->toBeArray();
    expect($service->analyzeFile('<script></script>', 'test.svelte'))->toBeArray();
    expect($service->analyzeFile('void main() {}', 'test.dart'))->toBeArray();
});

it('accepts supported shell extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('echo "test"', 'test.sh'))->toBeArray();
    expect($service->analyzeFile('echo "test"', 'test.bash'))->toBeArray();
    expect($service->analyzeFile('echo "test"', 'test.zsh'))->toBeArray();
});

it('accepts supported config extensions', function (): void {
    $service = new SemanticAnalyzerService;

    expect($service->analyzeFile('SELECT * FROM users', 'test.sql'))->toBeArray();
    expect($service->analyzeFile('key: value', 'test.yaml'))->toBeArray();
    expect($service->analyzeFile('key: value', 'test.yml'))->toBeArray();
});

it('analyzes a simple PHP function', function (): void {
    $result = $this->service->analyzeFile('<?php function calculateSum($a, $b) { return $a + $b; }', 'test.php');

    expect($result)->toBeArray()
        ->and($result['language'])->toBe('php')
        ->and($result['functions'])->toHaveCount(1)
        ->and($result['functions'][0]['name'])->toBe('calculateSum')
        ->and($result['functions'][0]['parameters'])->toHaveCount(2)
        ->and($result['functions'][0]['parameters'][0]['name'])->toBe('$a')
        ->and($result['functions'][0]['parameters'][1]['name'])->toBe('$b');
});

it('analyzes a PHP class with methods', function (): void {
    $code = '<?php
class UserService {
    public function getUser($id) {
        return User::find($id);
    }

    private function validateId($id) {
        return is_numeric($id);
    }
}';

    $result = $this->service->analyzeFile($code, 'UserService.php');

    expect($result)->toBeArray()
        ->and($result['language'])->toBe('php')
        ->and($result['classes'])->toHaveCount(1)
        ->and($result['classes'][0]['name'])->toBe('UserService')
        ->and($result['classes'][0]['methods'])->toHaveCount(2)
        ->and($result['classes'][0]['methods'][0]['name'])->toBe('getUser')
        ->and($result['classes'][0]['methods'][1]['name'])->toBe('validateId');
});

it('analyzes JavaScript functions and calls', function (): void {
    $code = 'function greet(name) {
  console.log(`Hello, ${name}`);
  return formatMessage(name);
}

function formatMessage(name) {
  return `Welcome ${name}!`;
}';

    $result = $this->service->analyzeFile($code, 'greet.js');

    expect($result)->toBeArray()
        ->and($result['language'])->toBe('javascript')
        ->and($result['functions'])->toHaveCount(2)
        ->and($result['functions'][0]['name'])->toBe('greet')
        ->and($result['calls'])->toHaveCount(2); // console.log and formatMessage
});

it('analyzes TypeScript with types', function (): void {
    $code = 'function add(a: number, b: number): number {
  return a + b;
}

class Calculator {
  multiply(x: number, y: number): number {
    return x * y;
  }
}';

    $result = $this->service->analyzeFile($code, 'calculator.ts');

    expect($result)->toBeArray()
        ->and($result['language'])->toBe('typescript')
        ->and($result['functions'])->toHaveCount(1)
        ->and($result['functions'][0]['name'])->toBe('add')
        ->and($result['classes'])->toHaveCount(1)
        ->and($result['classes'][0]['name'])->toBe('Calculator');
});

it('analyzes Python code', function (): void {
    $code = 'def calculate_total(items):
    return sum(items)

class Order:
    def process(self):
        return True';

    $result = $this->service->analyzeFile($code, 'order.py');

    expect($result)->toBeArray()
        ->and($result['language'])->toBe('python')
        ->and($result['functions'])->toHaveCount(2) // calculate_total + process method
        ->and($result['functions'][0]['name'])->toBe('calculate_total')
        ->and($result['classes'])->toHaveCount(1)
        ->and($result['classes'][0]['name'])->toBe('Order');
});

it('detects syntax errors in code', function (): void {
    $code = '<?php function broken( { return "missing closing paren"; }';

    $result = $this->service->analyzeFile($code, 'broken.php');

    expect($result)->toBeArray()
        ->and($result['errors'])->not()->toBeEmpty();
});

it('analyzes multiple files in batch', function (): void {
    $files = [
        'file1.php' => '<?php function test1() {}',
        'file2.php' => '<?php class TestClass {}',
        'file3.js' => 'function test3() {}',
        'file.txt' => 'not a code file', // Should be skipped
    ];

    $results = $this->service->analyzeFiles($files);

    expect($results)->toHaveCount(3)
        ->and($results)->toHaveKeys(['file1.php', 'file2.php', 'file3.js'])
        ->and($results)->not()->toHaveKey('file.txt')
        ->and($results['file1.php']['functions'])->toHaveCount(1)
        ->and($results['file2.php']['classes'])->toHaveCount(1)
        ->and($results['file3.js']['functions'])->toHaveCount(1);
});

it('extracts function calls correctly', function (): void {
    $code = '<?php
function process() {
    $data = fetchData();
    $result = validateData($data);
    return saveData($result);
}';

    $result = $this->service->analyzeFile($code, 'process.php');

    expect($result)->toBeArray()
        ->and($result['calls'])->toHaveCount(3)
        ->and($result['calls'][0]['callee'])->toBe('fetchData')
        ->and($result['calls'][1]['callee'])->toBe('validateData')
        ->and($result['calls'][2]['callee'])->toBe('saveData');
});

it('supports various file extensions', function (string $extension, bool $shouldSupport): void {
    $filename = "test.{$extension}";
    $result = $this->service->analyzeFile('const x = 1;', $filename);

    if ($shouldSupport) {
        // Should return an array for supported extensions
        expect($result)->toBeArray();
    } else {
        // Should return null for unsupported extensions
        expect($result)->toBeNull();
    }
})->with([
    ['php', true],
    ['js', true],
    ['mjs', true],
    ['ts', true],
    ['tsx', true],
    ['jsx', true],
    ['py', true],
    ['go', true],
    ['rs', true],
    ['txt', false],
    ['md', false],
    ['pdf', false],
]);
