package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzePerl analyzes Perl source code and extracts semantic information
func AnalyzePerl(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "perl",
		Functions: extractPerlFunctions(sourceStr, lines),
		Classes:   extractPerlPackages(sourceStr, lines),
		Imports:   extractPerlImports(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractPerlFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match sub declarations
	subRegex := regexp.MustCompile(`(?m)^\s*sub\s+(\w+)\s*(?:\([^)]*\))?\s*\{`)

	matches := subRegex.FindAllStringSubmatchIndex(source, -1)
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

	return functions
}

func extractPerlPackages(source string, lines []string) []types.ClassInfo {
	var packages []types.ClassInfo

	// Match package declarations
	pkgRegex := regexp.MustCompile(`(?m)^\s*package\s+([\w:]+)`)

	matches := pkgRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			packages = append(packages, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	return packages
}

func extractPerlImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// use statements
	useRegex := regexp.MustCompile(`(?m)^\s*use\s+([\w:]+)`)
	matches := useRegex.FindAllStringSubmatchIndex(source, -1)
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

	// require statements
	reqRegex := regexp.MustCompile(`(?m)^\s*require\s+['"]?([^'"\s;]+)['"]?`)
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

	return imports
}
