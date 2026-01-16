package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeHaskell analyzes Haskell source code and extracts semantic information
func AnalyzeHaskell(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "haskell",
		Functions: extractHaskellFunctions(sourceStr, lines),
		Classes:   extractHaskellTypes(sourceStr, lines),
		Imports:   extractHaskellImports(sourceStr, lines),
		Symbols:   extractHaskellSymbols(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractHaskellFunctions(source string, lines []string) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Match function type signatures: functionName :: Type -> Type
	sigRegex := regexp.MustCompile(`(?m)^(\w+)\s*::\s*(.+)$`)

	matches := sigRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 6 {
			name := source[match[2]:match[3]]
			typeStr := source[match[4]:match[5]]
			line := strings.Count(source[:match[0]], "\n") + 1

			// Skip type class declarations
			if name == "class" || name == "instance" || name == "data" || name == "type" || name == "newtype" {
				continue
			}

			// Parse return type from type signature
			returnType := parseHaskellReturnType(typeStr)

			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				ReturnType: returnType,
			})
		}
	}

	// Match function definitions without type signatures: functionName args = expr
	defRegex := regexp.MustCompile(`(?m)^(\w+)(?:\s+\w+)*\s*=\s*`)

	matches = defRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			// Skip keywords and already found functions
			if name == "module" || name == "import" || name == "data" || name == "type" ||
				name == "newtype" || name == "class" || name == "instance" || name == "where" ||
				name == "let" || name == "in" || name == "if" || name == "then" || name == "else" {
				continue
			}

			// Check if we already have this function from type signature
			found := false
			for _, f := range functions {
				if f.Name == name {
					found = true
					break
				}
			}

			if !found {
				functions = append(functions, types.FunctionInfo{
					Name:      name,
					LineStart: line,
				})
			}
		}
	}

	return functions
}

func extractHaskellTypes(source string, lines []string) []types.ClassInfo {
	var types_ []types.ClassInfo

	// Match data declarations: data TypeName = ...
	dataRegex := regexp.MustCompile(`(?m)^data\s+(\w+)(?:\s+\w+)*`)
	matches := dataRegex.FindAllStringSubmatchIndex(source, -1)
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

	// Match newtype declarations: newtype TypeName = ...
	newtypeRegex := regexp.MustCompile(`(?m)^newtype\s+(\w+)(?:\s+\w+)*`)
	matches = newtypeRegex.FindAllStringSubmatchIndex(source, -1)
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

	// Match type synonyms: type TypeName = ...
	typeRegex := regexp.MustCompile(`(?m)^type\s+(\w+)(?:\s+\w+)*\s*=`)
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

	// Match class declarations: class ClassName where
	classRegex := regexp.MustCompile(`(?m)^class\s+(?:[^=]*=>\s*)?(\w+)`)
	matches = classRegex.FindAllStringSubmatchIndex(source, -1)
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

func extractHaskellImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// import statements
	importRegex := regexp.MustCompile(`(?m)^import\s+(?:qualified\s+)?([A-Z][\w.]*)`)
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

func extractHaskellSymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Module declaration
	moduleRegex := regexp.MustCompile(`(?m)^module\s+([A-Z][\w.]*)`)
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

	return symbols
}

func parseHaskellReturnType(typeStr string) string {
	// The return type is the last type in a function signature
	// e.g., "Int -> String -> Bool" returns "Bool"
	parts := strings.Split(typeStr, "->")
	if len(parts) > 0 {
		return strings.TrimSpace(parts[len(parts)-1])
	}
	return typeStr
}
