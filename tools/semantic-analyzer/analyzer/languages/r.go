package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeR analyzes R source code and extracts semantic information
func AnalyzeR(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "r",
		Functions: extractRFunctions(sourceStr, lines),
		Imports:   extractRImports(sourceStr, lines),
		Symbols:   extractRSymbols(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractRFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match function assignments: name <- function(params) or name = function(params)
	funcRegex := regexp.MustCompile(`(?m)^\s*(\w+)\s*(?:<-|=)\s*function\s*\(([^)]*)\)`)

	matches := funcRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[2]:match[3]]
			paramsStr := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			params := parseRParams(paramsStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	return functions
}

func extractRImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// library() calls
	libRegex := regexp.MustCompile(`(?m)library\s*\(\s*["']?(\w+)["']?\s*\)`)
	matches := libRegex.FindAllStringSubmatchIndex(source, -1)
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

	// require() calls
	reqRegex := regexp.MustCompile(`(?m)require\s*\(\s*["']?(\w+)["']?\s*\)`)
	matches = reqRegex.FindAllStringSubmatchIndex(source, -1)
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

	// source() calls
	srcRegex := regexp.MustCompile(`(?m)source\s*\(\s*["']([^"']+)["']\s*\)`)
	matches = srcRegex.FindAllStringSubmatchIndex(source, -1)
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

func extractRSymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Variable assignments
	varRegex := regexp.MustCompile(`(?m)^\s*(\w+)\s*(?:<-|=)\s*[^f]`)
	matches := varRegex.FindAllStringSubmatchIndex(source, -1)
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

func parseRParams(paramsStr string) []types.ParameterInfo {
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

		// Handle default values: param = value
		name := strings.Split(part, "=")[0]
		name = strings.TrimSpace(name)

		params = append(params, types.ParameterInfo{
			Name: name,
		})
	}

	return params
}
