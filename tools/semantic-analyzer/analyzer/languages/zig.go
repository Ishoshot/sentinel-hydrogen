package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeZig analyzes Zig source code and extracts semantic information
func AnalyzeZig(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "zig",
		Functions: extractZigFunctions(sourceStr, lines),
		Classes:   extractZigStructs(sourceStr, lines),
		Imports:   extractZigImports(sourceStr, lines),
		Symbols:   extractZigSymbols(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractZigFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match function declarations: pub fn name(params) ReturnType or fn name(params) ReturnType
	funcRegex := regexp.MustCompile(`(?m)^[\t ]*(pub\s+)?fn\s+(\w+)\s*\(([^)]*)\)\s*(\w+)?`)

	matches := funcRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			isPublic := match[2] != -1 && match[3] != -1
			name := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			paramsStr := ""
			if match[6] != -1 && match[7] != -1 {
				paramsStr = source[match[6]:match[7]]
			}

			returnType := ""
			if len(match) >= 10 && match[8] != -1 && match[9] != -1 {
				returnType = source[match[8]:match[9]]
			}

			params := parseZigParams(paramsStr)

			visibility := "private"
			if isPublic {
				visibility = "public"
			}

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
				ReturnType: returnType,
				Visibility: visibility,
			})
		}
	}

	return functions
}

func extractZigStructs(source string, lines []string) []types.ClassInfo {
	var structs []types.ClassInfo

	// Match struct declarations: pub const Name = struct { or const Name = struct {
	structRegex := regexp.MustCompile(`(?m)^[\t ]*(pub\s+)?const\s+(\w+)\s*=\s*struct\s*\{`)

	matches := structRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			structs = append(structs, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	// Match enum declarations
	enumRegex := regexp.MustCompile(`(?m)^[\t ]*(pub\s+)?const\s+(\w+)\s*=\s*enum\s*\{`)
	matches = enumRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			structs = append(structs, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	// Match union declarations
	unionRegex := regexp.MustCompile(`(?m)^[\t ]*(pub\s+)?const\s+(\w+)\s*=\s*union\s*(?:\([^)]*\))?\s*\{`)
	matches = unionRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			structs = append(structs, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	return structs
}

func extractZigImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// @import statements
	importRegex := regexp.MustCompile(`(?m)@import\s*\(\s*"([^"]+)"\s*\)`)
	matches := importRegex.FindAllStringSubmatchIndex(source, -1)
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

	// @cImport statements
	cImportRegex := regexp.MustCompile(`(?m)@cImport\s*\(`)
	matches = cImportRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		line := strings.Count(source[:match[0]], "\n") + 1
		imports = append(imports, types.ImportInfo{
			Module: "<c-import>",
			Line:   line,
		})
	}

	return imports
}

func extractZigSymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Constant declarations
	constRegex := regexp.MustCompile(`(?m)^[\t ]*(pub\s+)?const\s+(\w+)\s*(?::\s*(\w+))?\s*=\s*[^se]`)
	matches := constRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			// Skip if it's a struct/enum/union definition
			if strings.Contains(source[match[0]:min(match[1]+20, len(source))], "struct") ||
				strings.Contains(source[match[0]:min(match[1]+20, len(source))], "enum") ||
				strings.Contains(source[match[0]:min(match[1]+20, len(source))], "union") {
				continue
			}

			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "constant",
				Line: line,
			})
		}
	}

	// Variable declarations
	varRegex := regexp.MustCompile(`(?m)^[\t ]*var\s+(\w+)\s*(?::\s*(\w+))?`)
	matches = varRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "variable",
				Line: line,
			})
		}
	}

	return symbols
}

func parseZigParams(paramsStr string) []types.ParameterInfo {
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

		// Format: name: Type
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

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}
