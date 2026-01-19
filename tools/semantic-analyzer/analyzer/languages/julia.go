package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeJulia analyzes Julia source code and extracts semantic information
func AnalyzeJulia(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "julia",
		Functions: extractJuliaFunctions(sourceStr, lines),
		Classes:   extractJuliaStructs(sourceStr, lines),
		Imports:   extractJuliaImports(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractJuliaFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match function declarations
	funcRegex := regexp.MustCompile(`(?m)^\s*function\s+(\w+)(?:{[^}]*})?\s*\(([^)]*)\)`)

	matches := funcRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[2]:match[3]]
			paramsStr := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			params := parseJuliaParams(paramsStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	// Also match short-form functions: f(x) = expr
	shortFuncRegex := regexp.MustCompile(`(?m)^\s*(\w+)\s*\(([^)]*)\)\s*=`)
	matches = shortFuncRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[2]:match[3]]
			paramsStr := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			params := parseJuliaParams(paramsStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	return functions
}

func extractJuliaStructs(source string, lines []string) []types.ClassInfo {
	var structs []types.ClassInfo

	// Match struct declarations
	structRegex := regexp.MustCompile(`(?m)^\s*(?:(mutable)\s+)?struct\s+(\w+)(?:\s*<:\s*(\w+))?`)

	matches := structRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := ""
			if match[4] != -1 && match[5] != -1 {
				name = source[match[4]:match[5]]
			}

			extends := ""
			if len(match) >= 8 && match[6] != -1 && match[7] != -1 {
				extends = source[match[6]:match[7]]
			}

			line := strings.Count(source[:match[0]], "\n") + 1

			if name != "" {
				structs = append(structs, types.ClassInfo{
					Name:      name,
					LineStart: line,
					Extends:   extends,
				})
			}
		}
	}

	// Also match abstract types
	abstractRegex := regexp.MustCompile(`(?m)^\s*abstract\s+type\s+(\w+)(?:\s*<:\s*(\w+))?`)
	matches = abstractRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			structs = append(structs, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	return structs
}

func extractJuliaImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// using statements
	usingRegex := regexp.MustCompile(`(?m)^\s*using\s+([\w.,\s]+)`)
	matches := usingRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			modules := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			parts := strings.Split(modules, ",")
			for _, part := range parts {
				part = strings.TrimSpace(part)
				if part != "" {
					imports = append(imports, types.ImportInfo{
						Module: part,
						Line:   line,
					})
				}
			}
		}
	}

	// import statements
	importRegex := regexp.MustCompile(`(?m)^\s*import\s+([\w.,\s]+)`)
	matches = importRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			modules := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			parts := strings.Split(modules, ",")
			for _, part := range parts {
				part = strings.TrimSpace(part)
				if part != "" {
					imports = append(imports, types.ImportInfo{
						Module: part,
						Line:   line,
					})
				}
			}
		}
	}

	// include statements
	includeRegex := regexp.MustCompile(`(?m)^\s*include\s*\(\s*["']([^"']+)["']\s*\)`)
	matches = includeRegex.FindAllStringSubmatchIndex(source, -1)
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

func parseJuliaParams(paramsStr string) []types.ParameterInfo {
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

		// Handle type annotations: param::Type and defaults
		name := part
		typeName := ""

		if idx := strings.Index(part, "::"); idx != -1 {
			name = strings.TrimSpace(part[:idx])
			typeAndDefault := part[idx+2:]
			if eqIdx := strings.Index(typeAndDefault, "="); eqIdx != -1 {
				typeName = strings.TrimSpace(typeAndDefault[:eqIdx])
			} else {
				typeName = strings.TrimSpace(typeAndDefault)
			}
		} else if idx := strings.Index(part, "="); idx != -1 {
			name = strings.TrimSpace(part[:idx])
		}

		params = append(params, types.ParameterInfo{
			Name: name,
			Type: typeName,
		})
	}

	return params
}
