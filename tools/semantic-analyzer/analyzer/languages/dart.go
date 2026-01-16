package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeDart analyzes Dart source code and extracts semantic information
func AnalyzeDart(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "dart",
		Functions: extractDartFunctions(sourceStr, lines),
		Classes:   extractDartClasses(sourceStr, lines),
		Imports:   extractDartImports(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractDartFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match function declarations: ReturnType functionName(params) { or async
	funcRegex := regexp.MustCompile(`(?m)^\s*(?:(static)\s+)?(?:(\w+(?:<[^>]+>)?)\s+)?(\w+)\s*\(([^)]*)\)\s*(?:async\s*)?[{=]`)

	matches := funcRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 8 {
			continue
		}

		isStatic := match[2] != -1 && match[3] != -1

		returnType := ""
		if match[4] != -1 && match[5] != -1 {
			returnType = source[match[4]:match[5]]
		}

		name := ""
		if match[6] != -1 && match[7] != -1 {
			name = source[match[6]:match[7]]
		}

		// Skip class/if/while/for/switch keywords
		if name == "class" || name == "if" || name == "while" || name == "for" || name == "switch" || name == "catch" {
			continue
		}

		paramsStr := ""
		if match[8] != -1 && match[9] != -1 {
			paramsStr = source[match[8]:match[9]]
		}

		params := parseDartParams(paramsStr)
		line := strings.Count(source[:match[0]], "\n") + 1

		if name != "" {
			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
				ReturnType: returnType,
				IsStatic:   isStatic,
			})
		}
	}

	return functions
}

func extractDartClasses(source string, lines []string) []types.ClassInfo {
	var classes []types.ClassInfo

	// Match class declarations
	classRegex := regexp.MustCompile(`(?m)^\s*(?:(abstract)\s+)?class\s+(\w+)(?:<[^>]+>)?(?:\s+extends\s+(\w+))?(?:\s+(?:implements|with)\s+([^{]+))?`)

	matches := classRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 6 {
			continue
		}

		name := ""
		if match[4] != -1 && match[5] != -1 {
			name = source[match[4]:match[5]]
		}

		extends := ""
		if len(match) >= 8 && match[6] != -1 && match[7] != -1 {
			extends = source[match[6]:match[7]]
		}

		var implements []string
		if len(match) >= 10 && match[8] != -1 && match[9] != -1 {
			implStr := source[match[8]:match[9]]
			parts := strings.Split(implStr, ",")
			for _, p := range parts {
				p = strings.TrimSpace(p)
				if p != "" {
					implements = append(implements, p)
				}
			}
		}

		line := strings.Count(source[:match[0]], "\n") + 1

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

func extractDartImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	importRegex := regexp.MustCompile(`(?m)^import\s+['"]([^'"]+)['"]`)

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

	return imports
}

func parseDartParams(paramsStr string) []types.ParameterInfo {
	var params []types.ParameterInfo

	if strings.TrimSpace(paramsStr) == "" {
		return params
	}

	// Split by comma (careful with generics)
	depth := 0
	current := ""
	for _, c := range paramsStr {
		if c == '<' || c == '(' || c == '[' || c == '{' {
			depth++
		} else if c == '>' || c == ')' || c == ']' || c == '}' {
			depth--
		}
		if c == ',' && depth == 0 {
			if trimmed := strings.TrimSpace(current); trimmed != "" {
				params = append(params, parseDartParam(trimmed))
			}
			current = ""
		} else {
			current += string(c)
		}
	}
	if trimmed := strings.TrimSpace(current); trimmed != "" {
		params = append(params, parseDartParam(trimmed))
	}

	return params
}

func parseDartParam(param string) types.ParameterInfo {
	// Remove required/this/super keywords
	param = strings.TrimPrefix(param, "required ")
	param = strings.TrimPrefix(param, "this.")
	param = strings.TrimPrefix(param, "super.")

	// Format: Type name or Type? name or name = default
	parts := strings.Fields(param)
	if len(parts) >= 2 {
		return types.ParameterInfo{
			Name: strings.Split(parts[len(parts)-1], "=")[0],
			Type: strings.Join(parts[:len(parts)-1], " "),
		}
	} else if len(parts) == 1 {
		return types.ParameterInfo{
			Name: strings.Split(parts[0], "=")[0],
		}
	}

	return types.ParameterInfo{}
}
