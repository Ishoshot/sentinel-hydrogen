package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeGroovy analyzes Groovy source code and extracts semantic information
func AnalyzeGroovy(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "groovy",
		Functions: extractGroovyFunctions(sourceStr, lines),
		Classes:   extractGroovyClasses(sourceStr, lines),
		Imports:   extractGroovyImports(sourceStr, lines),
		Symbols:   extractGroovySymbols(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractGroovyFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match method declarations: def methodName(params) or ReturnType methodName(params)
	methodRegex := regexp.MustCompile(`(?m)^[\t ]*((?:public|private|protected|static|final|synchronized|abstract)\s+)*(def|void|[\w<>,\[\]]+)\s+(\w+)\s*\(([^)]*)\)`)

	matches := methodRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 10 {
			continue
		}

		returnType := ""
		if match[4] != -1 && match[5] != -1 {
			returnType = source[match[4]:match[5]]
		}

		name := ""
		if match[6] != -1 && match[7] != -1 {
			name = source[match[6]:match[7]]
		}

		// Skip keywords that match
		if name == "class" || name == "interface" || name == "if" || name == "while" ||
			name == "for" || name == "switch" || name == "catch" || name == "trait" {
			continue
		}

		paramsStr := ""
		if match[8] != -1 && match[9] != -1 {
			paramsStr = source[match[8]:match[9]]
		}

		params := parseGroovyParams(paramsStr)
		line := strings.Count(source[:match[0]], "\n") + 1

		modifiers := ""
		if match[2] != -1 && match[3] != -1 {
			modifiers = source[match[2]:match[3]]
		}

		isStatic := strings.Contains(modifiers, "static")
		visibility := "public"
		if strings.Contains(modifiers, "private") {
			visibility = "private"
		} else if strings.Contains(modifiers, "protected") {
			visibility = "protected"
		}

		if name != "" {
			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
				ReturnType: returnType,
				IsStatic:   isStatic,
				Visibility: visibility,
			})
		}
	}

	// Match closures assigned to variables: def closureName = { params -> ... }
	closureRegex := regexp.MustCompile(`(?m)^[\t ]*(?:def|final)\s+(\w+)\s*=\s*\{`)
	matches = closureRegex.FindAllStringSubmatchIndex(source, -1)
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

func extractGroovyClasses(source string, lines []string) []types.ClassInfo {
	var classes []types.ClassInfo

	// Match class declarations
	classRegex := regexp.MustCompile(`(?m)^[\t ]*((?:public|private|protected|abstract|final|static)\s+)*(class|interface|trait|enum)\s+(\w+)(?:\s+extends\s+(\w+))?(?:\s+implements\s+([^{]+))?`)

	matches := classRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 8 {
			continue
		}

		name := ""
		if match[6] != -1 && match[7] != -1 {
			name = source[match[6]:match[7]]
		}

		extends := ""
		if len(match) >= 10 && match[8] != -1 && match[9] != -1 {
			extends = source[match[8]:match[9]]
		}

		var implements []string
		if len(match) >= 12 && match[10] != -1 && match[11] != -1 {
			implStr := source[match[10]:match[11]]
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

func extractGroovyImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	importRegex := regexp.MustCompile(`(?m)^[\t ]*import\s+(?:static\s+)?([\w.]+)(?:\.\*)?`)

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

func extractGroovySymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Field declarations: def fieldName or Type fieldName
	fieldRegex := regexp.MustCompile(`(?m)^[\t ]*((?:public|private|protected|static|final)\s+)*(def|[\w<>,\[\]]+)\s+(\w+)\s*=`)
	matches := fieldRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) < 8 {
			continue
		}

		name := ""
		if match[6] != -1 && match[7] != -1 {
			name = source[match[6]:match[7]]
		}

		if name == "" || name == "class" || name == "interface" || name == "trait" {
			continue
		}

		line := strings.Count(source[:match[0]], "\n") + 1
		symbols = append(symbols, types.SymbolInfo{
			Name: name,
			Kind: "field",
			Line: line,
		})
	}

	// Constants: static final Type NAME = value
	constRegex := regexp.MustCompile(`(?m)^[\t ]*(?:public\s+|private\s+|protected\s+)?static\s+final\s+[\w<>,\[\]]+\s+(\w+)\s*=`)
	matches = constRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "constant",
				Line: line,
			})
		}
	}

	return symbols
}

func parseGroovyParams(paramsStr string) []types.ParameterInfo {
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

		// Handle default values
		if idx := strings.Index(part, "="); idx != -1 {
			part = strings.TrimSpace(part[:idx])
		}

		// Format: Type name or def name or just name
		fields := strings.Fields(part)
		if len(fields) >= 2 {
			typeName := fields[0]
			name := fields[len(fields)-1]
			params = append(params, types.ParameterInfo{
				Name: name,
				Type: typeName,
			})
		} else if len(fields) == 1 {
			params = append(params, types.ParameterInfo{
				Name: fields[0],
			})
		}
	}

	return params
}
