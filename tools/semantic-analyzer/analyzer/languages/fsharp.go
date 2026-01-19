package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeFSharp analyzes F# source code and extracts semantic information
func AnalyzeFSharp(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "fsharp",
		Functions: extractFSharpFunctions(sourceStr, lines),
		Classes:   extractFSharpTypes(sourceStr, lines),
		Imports:   extractFSharpImports(sourceStr, lines),
		Symbols:   extractFSharpSymbols(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractFSharpFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match let bindings that are functions: let functionName params =
	letRegex := regexp.MustCompile(`(?m)^[\t ]*let\s+(?:rec\s+)?(\w+)(?:\s+\w+)+\s*=`)

	matches := letRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			functions = append(functions, types.FunctionInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	// Match member functions: member this.FunctionName(params) =
	memberRegex := regexp.MustCompile(`(?m)^[\t ]*(?:static\s+)?member\s+(?:\w+\.)?(\w+)\s*\(([^)]*)\)`)

	matches = memberRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			paramsStr := ""
			if len(match) >= 6 && match[4] != -1 && match[5] != -1 {
				paramsStr = source[match[4]:match[5]]
			}

			params := parseFSharpParams(paramsStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	return functions
}

func extractFSharpTypes(source string, lines []string) []types.ClassInfo {
	var types_ []types.ClassInfo

	// Match type declarations: type TypeName =
	typeRegex := regexp.MustCompile(`(?m)^[\t ]*type\s+(\w+)(?:\s*<[^>]+>)?(?:\s*\([^)]*\))?\s*=`)
	matches := typeRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			types_ = append(types_, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	// Match class declarations: type TypeName() =
	classRegex := regexp.MustCompile(`(?m)^[\t ]*type\s+(\w+)\s*\([^)]*\)\s*(?:as\s+\w+)?\s*=`)
	matches = classRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			// Check if already added
			found := false
			for _, t := range types_ {
				if t.Name == name {
					found = true
					break
				}
			}

			if !found {
				types_ = append(types_, types.ClassInfo{
					Name:      name,
					LineStart: line,
				})
			}
		}
	}

	// Match interface declarations
	interfaceRegex := regexp.MustCompile(`(?m)^[\t ]*type\s+(\w+)\s*=\s*interface`)
	matches = interfaceRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			found := false
			for _, t := range types_ {
				if t.Name == name {
					found = true
					break
				}
			}

			if !found {
				types_ = append(types_, types.ClassInfo{
					Name:      name,
					LineStart: line,
				})
			}
		}
	}

	return types_
}

func extractFSharpImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// open statements
	openRegex := regexp.MustCompile(`(?m)^[\t ]*open\s+([\w.]+)`)
	matches := openRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			module := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			imports = append(imports, types.ImportInfo{
				Module: module,
				Line:   line,
			})
		}
	}

	return imports
}

func extractFSharpSymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Module declarations
	moduleRegex := regexp.MustCompile(`(?m)^[\t ]*module\s+([\w.]+)`)
	matches := moduleRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "module",
				Line: line,
			})
		}
	}

	// Namespace declarations
	nsRegex := regexp.MustCompile(`(?m)^[\t ]*namespace\s+([\w.]+)`)
	matches = nsRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "namespace",
				Line: line,
			})
		}
	}

	// Value bindings (non-functions)
	valRegex := regexp.MustCompile(`(?m)^[\t ]*let\s+(\w+)\s*=\s*[^f]`)
	matches = valRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "value",
				Line: line,
			})
		}
	}

	return symbols
}

func parseFSharpParams(paramsStr string) []types.ParameterInfo {
	var params []types.ParameterInfo

	if strings.TrimSpace(paramsStr) == "" {
		return params
	}

	parts := strings.Split(paramsStr, ",")
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part == "" {
			continue
		}

		// Format: name: Type or name
		if idx := strings.Index(part, ":"); idx != -1 {
			name := strings.TrimSpace(part[:idx])
			typeName := strings.TrimSpace(part[idx+1:])
			params = append(params, types.ParameterInfo{
				Name: name,
				Type: typeName,
			})
		} else {
			params = append(params, types.ParameterInfo{
				Name: part,
			})
		}
	}

	return params
}
