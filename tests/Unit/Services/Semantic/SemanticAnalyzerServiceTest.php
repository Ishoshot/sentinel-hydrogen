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
    $result = $this->service->analyzeFile('<?php echo "test";', 'test.txt');

    expect($result)->toBeNull();
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
