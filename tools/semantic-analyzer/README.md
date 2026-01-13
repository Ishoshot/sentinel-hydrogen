# Semantic Analyzer

A Go-based semantic code analyzer that extracts structural information from source code files using tree-sitter parsers.

## Overview

The semantic analyzer parses source code and extracts:
- **Functions**: Name, parameters, return types, line numbers
- **Classes**: Name, methods, properties, inheritance
- **Imports**: Module names and imported symbols
- **Calls**: Function/method invocations with arguments
- **Syntax Errors**: Parse errors and issues

## Supported Languages

- PHP
- JavaScript / JSX
- TypeScript / TSX
- Python
- Go
- Rust

## Building

### Requirements

- Go 1.22 or later

### Build Commands

```bash
# From project root
composer build-analyzer

# Or manually
cd tools/semantic-analyzer
go build -o ../../bin/semantic-analyzer .
```

### CI/CD

The binary is automatically built in GitHub Actions (see `.github/workflows/tests.yml`).

## Usage

The binary reads JSON from stdin and outputs JSON to stdout.

### Input Format

```json
{
  "filename": "example.php",
  "content": "<?php function test() {}",
  "extension": "php"
}
```

### Output Format

```json
{
  "language": "php",
  "functions": [
    {
      "name": "test",
      "line_start": 1,
      "line_end": 1,
      "parameters": [],
      "return_type": "",
      "visibility": "public",
      "is_async": false,
      "is_static": false
    }
  ],
  "classes": [],
  "imports": [],
  "exports": [],
  "calls": [],
  "symbols": [],
  "errors": []
}
```

### Example

```bash
echo '{"filename":"test.php","content":"<?php function hello() {}","extension":"php"}' | ./bin/semantic-analyzer
```

## Integration with Laravel

The binary is called by `App\Services\Semantic\SemanticAnalyzerService` via Laravel's Process facade.

```php
$service = new SemanticAnalyzerService();
$result = $service->analyzeFile($content, $filename);
```

## Testing

```bash
# Run semantic analyzer tests
php artisan test --filter=Semantic

# Run only unit tests
php artisan test tests/Unit/Services/Semantic/
```

## Architecture

```
tools/semantic-analyzer/
├── main.go                 # Entry point, CLI handling
├── types/
│   └── types.go           # Shared type definitions
├── analyzer/
│   ├── analyzer.go        # Core orchestration
│   └── languages/
│       ├── common.go      # Shared utilities
│       ├── php.go         # PHP parser
│       ├── javascript.go  # JS/JSX parser
│       ├── typescript.go  # TS/TSX parser
│       ├── python.go      # Python parser
│       ├── golang.go      # Go parser
│       └── rust.go        # Rust parser
```

## Adding New Languages

1. Add language bindings to `go.mod`:
   ```go
   require github.com/smacker/go-tree-sitter/java v0.0.0-...
   ```

2. Create `analyzer/languages/java.go`:
   ```go
   func AnalyzeJava(source []byte) (*types.SemanticAnalysis, error) {
       // Implementation
   }
   ```

3. Add to `analyzer/analyzer.go`:
   ```go
   case "java":
       return languages.AnalyzeJava([]byte(content))
   ```

4. Run `go mod tidy` and rebuild

## Performance

- Single file analysis: < 100ms
- Batch of 15 files: < 1s
- Binary size: ~9MB (static)

## Dependencies

- [tree-sitter](https://github.com/tree-sitter/tree-sitter) - Parser generator
- [go-tree-sitter](https://github.com/smacker/go-tree-sitter) - Go bindings
- Language-specific tree-sitter parsers
