package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeClojure analyzes Clojure source code and extracts semantic information
func AnalyzeClojure(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "clojure",
		Functions: extractClojureFunctions(sourceStr, lines),
		Classes:   extractClojureTypes(sourceStr, lines),
		Imports:   extractClojureImports(sourceStr, lines),
		Symbols:   extractClojureSymbols(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractClojureFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match defn declarations: (defn function-name [params] ...)
	defnRegex := regexp.MustCompile(`(?m)\(\s*defn-?\s+([\w-]+)\s*(?:"[^"]*"\s*)?\[([^\]]*)\]`)

	matches := defnRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			paramsStr := ""
			if len(match) >= 6 && match[4] != -1 && match[5] != -1 {
				paramsStr = source[match[4]:match[5]]
			}

			params := parseClojureParams(paramsStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	// Match fn declarations: (fn [params] ...)
	fnRegex := regexp.MustCompile(`(?m)\(\s*fn\s+([\w-]+)?\s*\[([^\]]*)\]`)

	matches = fnRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		name := "<anonymous>"
		if len(match) >= 4 && match[2] != -1 && match[3] != -1 {
			name = source[match[2]:match[3]]
		}
		line := strings.Count(source[:match[0]], "\n") + 1

		paramsStr := ""
		if len(match) >= 6 && match[4] != -1 && match[5] != -1 {
			paramsStr = source[match[4]:match[5]]
		}

		params := parseClojureParams(paramsStr)

		if name != "<anonymous>" {
			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	// Match defmacro declarations
	macroRegex := regexp.MustCompile(`(?m)\(\s*defmacro\s+([\w-]+)\s*(?:"[^"]*"\s*)?\[([^\]]*)\]`)

	matches = macroRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			paramsStr := ""
			if len(match) >= 6 && match[4] != -1 && match[5] != -1 {
				paramsStr = source[match[4]:match[5]]
			}

			params := parseClojureParams(paramsStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				Parameters: params,
			})
		}
	}

	return functions
}

func extractClojureTypes(source string, lines []string) []types.ClassInfo {
	var types_ []types.ClassInfo

	// Match defrecord declarations
	recordRegex := regexp.MustCompile(`(?m)\(\s*defrecord\s+([\w-]+)\s*\[`)
	matches := recordRegex.FindAllStringSubmatchIndex(source, -1)
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

	// Match deftype declarations
	typeRegex := regexp.MustCompile(`(?m)\(\s*deftype\s+([\w-]+)\s*\[`)
	matches = typeRegex.FindAllStringSubmatchIndex(source, -1)
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

	// Match defprotocol declarations
	protoRegex := regexp.MustCompile(`(?m)\(\s*defprotocol\s+([\w-]+)`)
	matches = protoRegex.FindAllStringSubmatchIndex(source, -1)
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

	// Match definterface declarations
	ifaceRegex := regexp.MustCompile(`(?m)\(\s*definterface\s+([\w-]+)`)
	matches = ifaceRegex.FindAllStringSubmatchIndex(source, -1)
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

	return types_
}

func extractClojureImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// ns :require statements
	requireRegex := regexp.MustCompile(`(?m):require\s*\[\s*\[?([\w.-]+)`)
	matches := requireRegex.FindAllStringSubmatchIndex(source, -1)
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

	// require statements outside ns
	reqRegex := regexp.MustCompile(`(?m)\(\s*require\s+'\[?([\w.-]+)`)
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

	// use statements
	useRegex := regexp.MustCompile(`(?m)\(\s*use\s+'\[?([\w.-]+)`)
	matches = useRegex.FindAllStringSubmatchIndex(source, -1)
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

	// :import statements
	importRegex := regexp.MustCompile(`(?m):import\s*\[\s*\[?([\w.-]+)`)
	matches = importRegex.FindAllStringSubmatchIndex(source, -1)
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

func extractClojureSymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Namespace declarations
	nsRegex := regexp.MustCompile(`(?m)\(\s*ns\s+([\w.-]+)`)
	matches := nsRegex.FindAllStringSubmatchIndex(source, -1)
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

	// def declarations
	defRegex := regexp.MustCompile(`(?m)\(\s*def\s+([\w-]+)`)
	matches = defRegex.FindAllStringSubmatchIndex(source, -1)
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

	// defonce declarations
	defonceRegex := regexp.MustCompile(`(?m)\(\s*defonce\s+([\w-]+)`)
	matches = defonceRegex.FindAllStringSubmatchIndex(source, -1)
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

func parseClojureParams(paramsStr string) []types.ParameterInfo {
	var params []types.ParameterInfo

	if strings.TrimSpace(paramsStr) == "" {
		return params
	}

	// Clojure params are space-separated in vectors
	parts := strings.Fields(paramsStr)
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part == "" || part == "&" {
			continue
		}

		// Handle destructuring hints like :keys, :as
		if strings.HasPrefix(part, ":") {
			continue
		}

		params = append(params, types.ParameterInfo{
			Name: part,
		})
	}

	return params
}
