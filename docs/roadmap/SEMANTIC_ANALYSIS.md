# PRD: Semantic Code Analysis for Sentinel

> **Target Audience**: LLM Agent (Claude Opus 4.5 / Claude Code)
> **Project**: Sentinel - AI Code Review Platform
> **Stack**: Laravel 12, PHP 8.4, Redis, PostgreSQL, **Go 1.22+**

---

## Executive Summary

Implement semantic code analysis for Sentinel using **tree-sitter** parsers to provide the AI reviewer with structural understanding of code, not just text diffs. This enables detection of type mismatches, dead code, broken call chains, and other issues that text-based review cannot catch.

**Tree-sitter parsers already exist** - you are NOT writing parsers. You are integrating existing, battle-tested parsers via the `go-tree-sitter` library.

---

## Objectives

1. Parse code files into Abstract Syntax Trees (ASTs) using tree-sitter
2. Extract semantic information (symbols, types, call relationships)
3. Provide this context to the AI reviewer alongside the text diff
4. Support 15-20 popular programming languages

---

## Architecture Overview

The system consists of:

1. **semantic-analyzer** (Go binary) - Embedded binary that runs tree-sitter parsers
2. **SemanticAnalyzerService** (PHP) - Calls the Go binary via Laravel Process
3. **SemanticCollector** (PHP) - Context collector that orchestrates analysis
4. **ContextBag.semantics** - New property to store semantic data
5. **User prompt updates** - Display semantic context to AI

**Deployment**: The Go binary is built during CI and shipped with Laravel. No separate service deployment.

---

## Implementation Approach: Embedded Go Binary

Build a Go CLI binary that PHP calls via `Process::run()`.

**Why Go**:

-   Single binary deployment (no runtime dependencies)
-   Excellent tree-sitter bindings via `go-tree-sitter`
-   Fast compilation and execution
-   Easy for future maintainers
-   Built during CI, ships with Laravel

---

## Supported Languages (20)

Using `github.com/smacker/go-tree-sitter` with language bindings:

| Language   | Go Package                                                | Priority |
| ---------- | --------------------------------------------------------- | -------- |
| PHP        | `github.com/smacker/go-tree-sitter/php`                   | P0       |
| JavaScript | `github.com/smacker/go-tree-sitter/javascript`            | P0       |
| TypeScript | `github.com/smacker/go-tree-sitter/typescript/typescript` | P0       |
| Python     | `github.com/smacker/go-tree-sitter/python`                | P0       |
| Go         | `github.com/smacker/go-tree-sitter/golang`                | P0       |
| Rust       | `github.com/smacker/go-tree-sitter/rust`                  | P0       |
| Java       | `github.com/smacker/go-tree-sitter/java`                  | P1       |
| Kotlin     | `github.com/smacker/go-tree-sitter/kotlin`                | P1       |
| C#         | `github.com/smacker/go-tree-sitter/csharp`                | P1       |
| Ruby       | `github.com/smacker/go-tree-sitter/ruby`                  | P1       |
| Swift      | `github.com/smacker/go-tree-sitter/swift`                 | P1       |
| C          | `github.com/smacker/go-tree-sitter/c`                     | P1       |
| C++        | `github.com/smacker/go-tree-sitter/cpp`                   | P1       |
| HTML       | `github.com/smacker/go-tree-sitter/html`                  | P2       |
| CSS        | `github.com/smacker/go-tree-sitter/css`                   | P2       |
| Bash       | `github.com/smacker/go-tree-sitter/bash`                  | P2       |
| YAML       | `github.com/smacker/go-tree-sitter/yaml`                  | P2       |
| JSON       | `github.com/smacker/go-tree-sitter/json`                  | P2       |
| SQL        | Community parser                                          | P2       |
| Dockerfile | Community parser                                          | P2       |

---

## Component 1: Semantic Analyzer (Go Binary)

**Location**: `tools/semantic-analyzer/`

### Directory Structure

```
tools/semantic-analyzer/
├── go.mod
├── go.sum
├── main.go                 # Entry point, CLI handling
├── Makefile                # Build commands
├── analyzer/
│   ├── analyzer.go         # Core analysis orchestration
│   ├── types.go            # Shared type definitions
│   └── languages/
│       ├── php.go          # PHP-specific extraction
│       ├── javascript.go   # JS/TS extraction
│       ├── python.go       # Python extraction
│       ├── golang.go       # Go extraction
│       └── common.go       # Shared utilities
└── README.md
```

### Files to Create:

#### tools/semantic-analyzer/go.mod

```go
module github.com/your-org/sentinel/tools/semantic-analyzer

go 1.22

require (
    github.com/smacker/go-tree-sitter v0.0.0-20240625050157-a31a98a7c0f6
)
```

#### tools/semantic-analyzer/Makefile

```makefile
.PHONY: build build-linux build-darwin clean test

BINARY_NAME=semantic-analyzer
BUILD_DIR=../../bin

build:
	go build -o $(BUILD_DIR)/$(BINARY_NAME) .

build-linux:
	GOOS=linux GOARCH=amd64 go build -o $(BUILD_DIR)/$(BINARY_NAME)-linux-amd64 .

build-darwin:
	GOOS=darwin GOARCH=arm64 go build -o $(BUILD_DIR)/$(BINARY_NAME)-darwin-arm64 .

build-all: build-linux build-darwin

clean:
	rm -f $(BUILD_DIR)/$(BINARY_NAME)*

test:
	go test -v ./...
```

#### tools/semantic-analyzer/main.go

```go
package main

import (
	"bufio"
	"encoding/json"
	"fmt"
	"os"

	"github.com/your-org/sentinel/tools/semantic-analyzer/analyzer"
)

type Input struct {
	Filename  string `json:"filename"`
	Content   string `json:"content"`
	Extension string `json:"extension"`
}

func main() {
	reader := bufio.NewReader(os.Stdin)

	// Read JSON input from stdin
	var input Input
	decoder := json.NewDecoder(reader)
	if err := decoder.Decode(&input); err != nil {
		outputError(fmt.Sprintf("failed to parse input: %v", err))
		return
	}

	// Analyze the file
	result, err := analyzer.Analyze(input.Content, input.Filename, input.Extension)
	if err != nil {
		outputError(fmt.Sprintf("analysis failed: %v", err))
		return
	}

	// Output JSON result
	encoder := json.NewEncoder(os.Stdout)
	encoder.SetIndent("", "  ")
	encoder.Encode(result)
}

func outputError(msg string) {
	result := analyzer.SemanticAnalysis{
		Errors: []analyzer.SyntaxError{{Message: msg}},
	}
	json.NewEncoder(os.Stdout).Encode(result)
}
```

#### tools/semantic-analyzer/analyzer/types.go

The Go binary reads JSON from stdin and outputs JSON to stdout.

**Semantic Information to Extract**:

```typescript
interface SemanticAnalysis {
    language: string;
    functions: FunctionInfo[];
    classes: ClassInfo[];
    imports: ImportInfo[];
    exports: ExportInfo[];
    calls: CallInfo[];
    symbols: SymbolInfo[];
    errors: SyntaxError[];
}

interface FunctionInfo {
    name: string;
    line_start: number;
    line_end: number;
    parameters: ParameterInfo[];
    return_type?: string;
    visibility?: "public" | "private" | "protected";
    is_async: boolean;
    is_static: boolean;
    docstring?: string;
}

interface ClassInfo {
    name: string;
    line_start: number;
    line_end: number;
    extends?: string;
    implements: string[];
    methods: FunctionInfo[];
    properties: PropertyInfo[];
}

interface CallInfo {
    caller_function?: string;
    callee: string;
    line: number;
    arguments_count: number;
    is_method_call: boolean;
    receiver?: string;
}

interface ImportInfo {
    module: string;
    symbols: string[];
    line: number;
    is_default: boolean;
}
```

---

#### tools/semantic-analyzer/analyzer/types.go

```go
package analyzer

// SemanticAnalysis is the output structure returned to PHP
type SemanticAnalysis struct {
	Language  string        `json:"language"`
	Functions []FunctionInfo `json:"functions"`
	Classes   []ClassInfo    `json:"classes"`
	Imports   []ImportInfo   `json:"imports"`
	Exports   []ExportInfo   `json:"exports"`
	Calls     []CallInfo     `json:"calls"`
	Symbols   []SymbolInfo   `json:"symbols"`
	Errors    []SyntaxError  `json:"errors"`
}

type FunctionInfo struct {
	Name       string          `json:"name"`
	LineStart  int             `json:"line_start"`
	LineEnd    int             `json:"line_end"`
	Parameters []ParameterInfo `json:"parameters"`
	ReturnType string          `json:"return_type,omitempty"`
	Visibility string          `json:"visibility,omitempty"`
	IsAsync    bool            `json:"is_async"`
	IsStatic   bool            `json:"is_static"`
	Docstring  string          `json:"docstring,omitempty"`
}

type ClassInfo struct {
	Name       string         `json:"name"`
	LineStart  int            `json:"line_start"`
	LineEnd    int            `json:"line_end"`
	Extends    string         `json:"extends,omitempty"`
	Implements []string       `json:"implements"`
	Methods    []FunctionInfo `json:"methods"`
	Properties []PropertyInfo `json:"properties"`
}

type CallInfo struct {
	CallerFunction string `json:"caller_function,omitempty"`
	Callee         string `json:"callee"`
	Line           int    `json:"line"`
	ArgumentsCount int    `json:"arguments_count"`
	IsMethodCall   bool   `json:"is_method_call"`
	Receiver       string `json:"receiver,omitempty"`
}

type ImportInfo struct {
	Module    string   `json:"module"`
	Symbols   []string `json:"symbols"`
	Line      int      `json:"line"`
	IsDefault bool     `json:"is_default"`
}

type ExportInfo struct {
	Name string `json:"name"`
	Line int    `json:"line"`
}

type SymbolInfo struct {
	Name string `json:"name"`
	Kind string `json:"kind"`
	Line int    `json:"line"`
}

type ParameterInfo struct {
	Name string `json:"name"`
	Type string `json:"type,omitempty"`
}

type PropertyInfo struct {
	Name       string `json:"name"`
	Type       string `json:"type,omitempty"`
	Visibility string `json:"visibility,omitempty"`
}

type SyntaxError struct {
	Line    int    `json:"line"`
	Column  int    `json:"column"`
	Message string `json:"message"`
}
```

---

## Component 2: SemanticAnalyzerService (PHP)

**Location**: `app/Services/Semantic/SemanticAnalyzerService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Semantic;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

final class SemanticAnalyzerService
{
    private const string BINARY_PATH = 'bin/semantic-analyzer';
    private const int TIMEOUT_SECONDS = 30;

    /**
     * Analyze a single file and return semantic information.
     */
    public function analyzeFile(string $content, string $filename): ?array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (!$this->isSupported($extension)) {
            return null;
        }

        $binaryPath = $this->getBinaryPath();
        if (!file_exists($binaryPath)) {
            Log::warning('SemanticAnalyzer: Binary not found', ['path' => $binaryPath]);
            return null;
        }

        $input = json_encode([
            'filename' => $filename,
            'content' => $content,
            'extension' => $extension,
        ], JSON_THROW_ON_ERROR);

        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->input($input)
            ->run($binaryPath);

        if (!$result->successful()) {
            Log::warning('SemanticAnalyzer: Failed to analyze file', [
                'filename' => $filename,
                'error' => $result->errorOutput(),
            ]);
            return null;
        }

        $output = json_decode($result->output(), true);

        return is_array($output) ? $output : null;
    }

    /**
     * Analyze multiple files in batch (parallel execution).
     *
     * @param array<string, string> $files filename => content
     * @return array<string, array> filename => semantics
     */
    public function analyzeFiles(array $files): array
    {
        $results = [];

        foreach ($files as $filename => $content) {
            $result = $this->analyzeFile($content, $filename);
            if ($result !== null) {
                $results[$filename] = $result;
            }
        }

        return $results;
    }

    private function getBinaryPath(): string
    {
        return base_path(self::BINARY_PATH);
    }

    private function isSupported(string $extension): bool
    {
        return in_array($extension, [
            'php', 'js', 'mjs', 'ts', 'tsx', 'jsx',
            'py', 'go', 'rs', 'java', 'kt', 'cs',
            'rb', 'swift', 'c', 'cpp', 'h', 'hpp',
            'vue', 'svelte', 'html', 'css', 'scss',
            'sql', 'sh', 'bash', 'yaml', 'yml',
        ], true);
    }
}
```

---

## Component 3: SemanticCollector (PHP)

**Location**: `app/Services/Context/Collectors/SemanticCollector.php`

This collector:

1. Gets file contents from the existing FileContextCollector or fetches them
2. Calls SemanticAnalyzerService for each file
3. Stores results in ContextBag

```php
<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Semantic\SemanticAnalyzerService;
use Illuminate\Support\Facades\Log;

final readonly class SemanticCollector implements ContextCollector
{
    private const int MAX_FILES = 15;

    public function __construct(
        private SemanticAnalyzerService $semanticAnalyzer
    ) {}

    public function name(): string
    {
        return 'semantic';
    }

    public function priority(): int
    {
        return 80; // After FileContextCollector (85), before others
    }

    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    public function collect(ContextBag $bag, array $params): void
    {
        // Use file contents already collected
        if (empty($bag->fileContents)) {
            Log::debug('SemanticCollector: No file contents available');
            return;
        }

        $filesToAnalyze = array_slice($bag->fileContents, 0, self::MAX_FILES, true);
        $semantics = $this->semanticAnalyzer->analyzeFiles($filesToAnalyze);

        $bag->semantics = $semantics;

        Log::info('SemanticCollector: Analyzed files', [
            'files_analyzed' => count($semantics),
        ]);
    }
}
```

---

## Component 4: ContextBag Update

Add to `app/Services/Context/ContextBag.php`:

```php
// Add to constructor parameters:
/** @var array<string, array{language: string, functions: array, classes: array, imports: array, calls: array}> */
public array $semantics = [],

// Add to estimateTokens():
foreach ($this->semantics as $data) {
    $totalChars += mb_strlen(json_encode($data) ?: '');
}

// Add to toArray():
'semantics' => $this->semantics,
```

---

## Component 5: User Prompt Update

Add to `resources/views/prompts/review-user.blade.php`:

```blade
@if(isset($semantics) && count($semantics) > 0)
## Semantic Analysis

The following structural information was extracted from the changed files:

@foreach($semantics as $filename => $data)
### `{{ $filename }}`

@if(count($data['functions'] ?? []) > 0)
**Functions:**
@foreach($data['functions'] as $func)
- `{{ $func['name'] }}({{ count($func['parameters'] ?? []) }} params)` @ line {{ $func['line_start'] }}@if($func['return_type']) → {{ $func['return_type'] }}@endif

@endforeach
@endif

@if(count($data['classes'] ?? []) > 0)
**Classes:**
@foreach($data['classes'] as $class)
- `{{ $class['name'] }}`@if($class['extends']) extends `{{ $class['extends'] }}`@endif @ lines {{ $class['line_start'] }}-{{ $class['line_end'] }}
@endforeach
@endif

@if(count($data['calls'] ?? []) > 0)
**Function Calls ({{ count($data['calls']) }}):**
@foreach(array_slice($data['calls'], 0, 10) as $call)
- Line {{ $call['line'] }}: `{{ $call['callee'] }}()`@if($call['receiver']) on `{{ $call['receiver'] }}`@endif

@endforeach
@if(count($data['calls']) > 10)
_... and {{ count($data['calls']) - 10 }} more calls_
@endif
@endif

@if(count($data['errors'] ?? []) > 0)
**⚠️ Syntax Errors Detected:**
@foreach($data['errors'] as $error)
- Line {{ $error['line'] }}: {{ $error['message'] }}
@endforeach
@endif

@endforeach
@endif
```

---

## Component 6: System Prompt Update

Add to `resources/views/prompts/review-system.blade.php`:

```blade
## Semantic Context

When semantic analysis is provided, use it to:

1. **Verify call chains**: If a function is modified, check if callers handle the change correctly
2. **Detect type mismatches**: If return types changed, verify callers expect the new type
3. **Find dead code**: If a function is no longer called from anywhere visible, flag it
4. **Check import usage**: If something is imported but never used, or used but not imported
5. **Validate class relationships**: If inheritance/interface changes affect implementers

**Example findings enabled by semantic analysis:**

- "The `calculateTotal()` function now returns `float` instead of `int`, but the caller in `OrderController.php` line 45 casts it to `int`, which may cause precision loss."
- "The `UserService::delete()` method is called from 3 places, but 2 of them don't handle the new `CannotDeleteException` that was added."
- "The `validateInput()` function was removed but is still called in `FormHandler.php` line 23."
```

---

## Implementation Steps (For the LLM)

### Phase 1: Go Analyzer Setup (Day 1)

1. Create `tools/semantic-analyzer/` directory structure
2. Create `go.mod` with tree-sitter dependencies
3. Run `go mod tidy`
4. Create `analyzer/types.go` with all struct definitions
5. Create `analyzer/analyzer.go` with core orchestration
6. Create `main.go` that:
    - Reads JSON from stdin
    - Calls analyzer with content and extension
    - Outputs JSON to stdout

### Phase 2: Language Extractors (Day 1-2)

1. Create `analyzer/languages/common.go` - shared AST walking utilities
2. Create `analyzer/languages/php.go` - PHP-specific extraction
3. Create `analyzer/languages/javascript.go` - JS/TS extraction
4. Create `analyzer/languages/python.go` - Python extraction
5. Create `analyzer/languages/golang.go` - Go extraction
6. Add remaining P0/P1 languages

### Phase 3: Build & Test Go Binary (Day 2)

1. Create `Makefile` with build targets
2. Run `make build` to create local binary
3. Test binary with sample files:
    ```bash
    echo '{"filename":"test.php","content":"<?php function foo() {}","extension":"php"}' | ./bin/semantic-analyzer
    ```
4. Verify JSON output is correct

### Phase 4: PHP Integration (Day 2)

1. Create `SemanticAnalyzerService.php`
2. Create `SemanticCollector.php`
3. Update `ContextBag.php` with semantics property
4. Register collector in service provider

### Phase 5: Prompt Integration (Day 2-3)

1. Update `review-user.blade.php` to display semantic data
2. Update `review-system.blade.php` with semantic analysis instructions
3. Test end-to-end with a real PR

### Phase 6: CI/CD Setup (Day 3)

1. Add Go build step to GitHub Actions
2. Ensure binary is built before Laravel deploy
3. Test full pipeline

---

## CI/CD Configuration

### GitHub Actions Workflow

Add to `.github/workflows/deploy.yml` (or your deploy workflow):

```yaml
jobs:
    build-and-deploy:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v4

            - name: Setup Go
              uses: actions/setup-go@v5
              with:
                  go-version: "1.22"
                  cache-dependency-path: tools/semantic-analyzer/go.sum

            - name: Build Semantic Analyzer
              run: |
                  cd tools/semantic-analyzer
                  go build -o ../../bin/semantic-analyzer .

            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.4"

            - name: Install Composer Dependencies
              run: composer install --no-dev --optimize-autoloader

            # ... rest of your deploy steps
```

### .gitignore Update

Add to `.gitignore`:

```
# Semantic analyzer binary (built in CI)
/bin/semantic-analyzer
/bin/semantic-analyzer-*
```

### Local Development

For local development, build the binary once:

```bash
cd tools/semantic-analyzer
go build -o ../../bin/semantic-analyzer .
```

Or add to your `composer.json` scripts:

```json
{
    "scripts": {
        "build-analyzer": "cd tools/semantic-analyzer && go build -o ../../bin/semantic-analyzer ."
    }
}
```

---

## Go Implementation Details

### AST Node Types by Language

#### PHP

-   `function_definition` - Function declarations
-   `method_declaration` - Class methods
-   `class_declaration` - Class definitions
-   `function_call_expression` - Function calls
-   `member_call_expression` - Method calls
-   `use_declaration` - Imports

#### JavaScript/TypeScript

-   `function_declaration` - Function declarations
-   `arrow_function` - Arrow functions
-   `method_definition` - Class methods
-   `class_declaration` - Class definitions
-   `call_expression` - Function/method calls
-   `import_statement` - Imports
-   `export_statement` - Exports

#### Python

-   `function_definition` - Function definitions
-   `class_definition` - Class definitions
-   `call` - Function calls
-   `import_statement` - Imports
-   `import_from_statement` - From imports

#### Go

-   `function_declaration` - Function declarations
-   `method_declaration` - Method declarations
-   `type_declaration` - Type definitions
-   `call_expression` - Function calls
-   `import_declaration` - Imports

### Sample Go Walker

```go
// analyzer/languages/common.go
package languages

import (
	sitter "github.com/smacker/go-tree-sitter"
)

// WalkTree recursively walks the AST and calls the callback for each node
func WalkTree(node *sitter.Node, callback func(*sitter.Node)) {
	callback(node)
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil {
			WalkTree(child, callback)
		}
	}
}

// FindNodes finds all nodes of the given types
func FindNodes(root *sitter.Node, types []string) []*sitter.Node {
	var nodes []*sitter.Node
	typeSet := make(map[string]bool)
	for _, t := range types {
		typeSet[t] = true
	}

	WalkTree(root, func(node *sitter.Node) {
		if typeSet[node.Type()] {
			nodes = append(nodes, node)
		}
	})

	return nodes
}

// GetNodeText extracts the text content of a node
func GetNodeText(node *sitter.Node, source []byte) string {
	return string(source[node.StartByte():node.EndByte()])
}
```

```go
// analyzer/languages/php.go
package languages

import (
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/php"
)

func GetPHPParser() *sitter.Parser {
	parser := sitter.NewParser()
	parser.SetLanguage(php.GetLanguage())
	return parser
}

func ExtractPHPFunctions(root *sitter.Node, source []byte) []FunctionInfo {
	functionTypes := []string{"function_definition", "method_declaration"}
	nodes := FindNodes(root, functionTypes)

	var functions []FunctionInfo
	for _, node := range nodes {
		name := extractPHPFunctionName(node, source)
		functions = append(functions, FunctionInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			// ... extract parameters, return type, etc.
		})
	}

	return functions
}

func extractPHPFunctionName(node *sitter.Node, source []byte) string {
	// Find the 'name' child node
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil && child.Type() == "name" {
			return GetNodeText(child, source)
		}
	}
	return ""
}
```

---

## Error Handling

1. **Unsupported language**: Return empty result with language set, don't fail
2. **Parse errors**: Return partial results with `errors` array populated
3. **Timeout**: PHP Process timeout catches this, logs warning
4. **Binary not found**: Gracefully degrade, log warning, review continues without semantics
5. **Large files**: Skip files > 100KB in SemanticCollector

---

## Performance Considerations

1. **Parallel parsing**: Consider using goroutines for multiple files in Go
2. **Caching**: Consider caching parsed results by file hash
3. **Token limits**: Semantic data counts toward context tokens, truncate if needed
4. **Priority files**: Analyze files with most changes first

---

## Success Criteria

1. ✅ Parse PHP, JS, TS, Python, Go files without errors
2. ✅ Extract functions, classes, imports, calls for each language
3. ✅ Display semantic info in AI context
4. ✅ AI can use semantic info to make better findings
5. ✅ No significant increase in review latency (< 5s added)

---

## Files to Create/Modify

### Create (Go):

-   `tools/semantic-analyzer/go.mod`
-   `tools/semantic-analyzer/go.sum`
-   `tools/semantic-analyzer/main.go`
-   `tools/semantic-analyzer/Makefile`
-   `tools/semantic-analyzer/README.md`
-   `tools/semantic-analyzer/analyzer/analyzer.go`
-   `tools/semantic-analyzer/analyzer/types.go`
-   `tools/semantic-analyzer/analyzer/languages/common.go`
-   `tools/semantic-analyzer/analyzer/languages/php.go`
-   `tools/semantic-analyzer/analyzer/languages/javascript.go`
-   `tools/semantic-analyzer/analyzer/languages/typescript.go`
-   `tools/semantic-analyzer/analyzer/languages/python.go`
-   `tools/semantic-analyzer/analyzer/languages/golang.go`
-   `tools/semantic-analyzer/analyzer/languages/rust.go`
-   `tools/semantic-analyzer/analyzer/languages/java.go`

### Create (PHP):

-   `app/Services/Semantic/SemanticAnalyzerService.php`
-   `app/Services/Context/Collectors/SemanticCollector.php`
-   `tests/Unit/Services/Semantic/SemanticAnalyzerServiceTest.php`
-   `tests/Feature/Context/SemanticCollectorTest.php`

### Modify:

-   `app/Services/Context/ContextBag.php` - Add semantics property
-   `app/Providers/AppServiceProvider.php` - Register services
-   `resources/views/prompts/review-user.blade.php` - Display semantic data
-   `resources/views/prompts/review-system.blade.php` - Add semantic instructions
-   `.gitignore` - Add `/bin/semantic-analyzer`
-   `.github/workflows/deploy.yml` - Add Go build step

---

## Remember

-   **You are NOT writing parsers** - use `github.com/smacker/go-tree-sitter` which wraps existing tree-sitter parsers
-   **Single binary** - Go compiles to one binary, no runtime dependencies needed on server
-   **Build in CI** - The binary is built during deploy, not committed to repo
-   **Keep it simple** - Extract the most useful info first (functions, classes, calls)
-   **Graceful degradation** - If binary missing or parsing fails, review continues without semantics
-   **Token budget** - Semantic data counts toward context tokens, truncate intelligently
-   **Test with real code** - Use actual files from the Sentinel codebase

Good luck! 🚀
