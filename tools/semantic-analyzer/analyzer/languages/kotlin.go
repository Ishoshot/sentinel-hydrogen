package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeKotlin analyzes Kotlin source code and extracts semantic information
// Uses regex-based parsing as tree-sitter Kotlin support requires external bindings
func AnalyzeKotlin(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "kotlin",
		Functions: extractKotlinFunctions(sourceStr, lines),
		Classes:   extractKotlinClasses(sourceStr, lines),
		Imports:   extractKotlinImports(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractKotlinFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match fun declarations
	// fun name(params): ReturnType { or fun name(params) =
	funcRegex := regexp.MustCompile(`(?m)^\s*(?:(private|public|protected|internal)\s+)?(?:(suspend)\s+)?fun\s+(\w+)\s*(?:<[^>]+>)?\s*\(([^)]*)\)(?:\s*:\s*(\w+(?:<[^>]+>)?))?`)

	matches := funcRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 8 {
			continue
		}

		visibility := ""
		if match[2] != -1 && match[3] != -1 {
			visibility = source[match[2]:match[3]]
		}

		isAsync := false
		if match[4] != -1 && match[5] != -1 {
			isAsync = source[match[4]:match[5]] == "suspend"
		}

		name := ""
		if match[6] != -1 && match[7] != -1 {
			name = source[match[6]:match[7]]
		}

		paramsStr := ""
		if match[8] != -1 && match[9] != -1 {
			paramsStr = source[match[8]:match[9]]
		}

		returnType := ""
		if len(match) >= 12 && match[10] != -1 && match[11] != -1 {
			returnType = source[match[10]:match[11]]
		}

		params := parseKotlinParams(paramsStr)
		line := countKotlinLines(source[:match[0]]) + 1

		if name != "" {
			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
				ReturnType: returnType,
				Visibility: visibility,
				IsAsync:    isAsync,
			})
		}
	}

	return functions
}

func extractKotlinClasses(source string, lines []string) []types.ClassInfo {
	var classes []types.ClassInfo

	// Match class/interface/object/data class declarations
	classRegex := regexp.MustCompile(`(?m)^\s*(?:(private|public|protected|internal)\s+)?(?:(data|sealed|abstract|open)\s+)?(class|interface|object)\s+(\w+)(?:\s*:\s*([^{]+))?`)

	matches := classRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 10 {
			continue
		}

		name := ""
		if match[8] != -1 && match[9] != -1 {
			name = source[match[8]:match[9]]
		}

		extends := ""
		var implements []string
		if len(match) >= 12 && match[10] != -1 && match[11] != -1 {
			inheritance := strings.TrimSpace(source[match[10]:match[11]])
			// Split by comma and process
			parts := strings.Split(inheritance, ",")
			for i, part := range parts {
				part = strings.TrimSpace(part)
				// Remove generic parameters for simpler parsing
				if idx := strings.Index(part, "<"); idx != -1 {
					part = part[:idx]
				}
				if idx := strings.Index(part, "("); idx != -1 {
					part = part[:idx]
				}
				part = strings.TrimSpace(part)
				if i == 0 {
					extends = part
				} else {
					implements = append(implements, part)
				}
			}
		}

		line := countKotlinLines(source[:match[0]]) + 1

		if name != "" {
			classes = append(classes, types.ClassInfo{
				Name:       name,
				LineStart:  line,
				Extends:    extends,
				Implements: implements,
			})
		}
	}

	return classes
}

func extractKotlinImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	importRegex := regexp.MustCompile(`(?m)^import\s+([^\s]+)`)

	matches := importRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			module := source[match[2]:match[3]]
			line := countKotlinLines(source[:match[0]]) + 1
			imports = append(imports, types.ImportInfo{
				Module: module,
				Line:   line,
			})
		}
	}

	return imports
}

func parseKotlinParams(paramsStr string) []types.ParameterInfo {
	var params []types.ParameterInfo

	if strings.TrimSpace(paramsStr) == "" {
		return params
	}

	// Split by comma (careful with generics)
	depth := 0
	current := ""
	for _, c := range paramsStr {
		if c == '<' {
			depth++
		} else if c == '>' {
			depth--
		}
		if c == ',' && depth == 0 {
			if trimmed := strings.TrimSpace(current); trimmed != "" {
				params = append(params, parseKotlinParam(trimmed))
			}
			current = ""
		} else {
			current += string(c)
		}
	}
	if trimmed := strings.TrimSpace(current); trimmed != "" {
		params = append(params, parseKotlinParam(trimmed))
	}

	return params
}

func parseKotlinParam(param string) types.ParameterInfo {
	// Format: name: Type or vararg name: Type
	param = strings.TrimPrefix(param, "vararg ")

	parts := strings.SplitN(param, ":", 2)
	name := strings.TrimSpace(parts[0])
	typeName := ""
	if len(parts) > 1 {
		typeName = strings.TrimSpace(parts[1])
		// Remove default value if present
		if idx := strings.Index(typeName, "="); idx != -1 {
			typeName = strings.TrimSpace(typeName[:idx])
		}
	}

	return types.ParameterInfo{
		Name: name,
		Type: typeName,
	}
}

func countKotlinLines(s string) int {
	return strings.Count(s, "\n")
}
