package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeObjectiveC analyzes Objective-C source code and extracts semantic information
func AnalyzeObjectiveC(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language:  "objective-c",
		Functions: extractObjCMethods(sourceStr, lines),
		Classes:   extractObjCClasses(sourceStr, lines),
		Imports:   extractObjCImports(sourceStr, lines),
		Errors:    []types.SyntaxError{},
	}

	return result, nil
}

func extractObjCMethods(source string, lines []string) []types.FunctionInfo {
	var methods []types.FunctionInfo

	// Match method declarations: - (returnType)methodName or + (returnType)methodName
	methodRegex := regexp.MustCompile(`(?m)^[\t ]*([+-])\s*\(([^)]+)\)\s*(\w+)`)

	matches := methodRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 8 {
			isStatic := source[match[2]:match[3]] == "+"
			returnType := source[match[4]:match[5]]
			name := source[match[6]:match[7]]
			line := strings.Count(source[:match[0]], "\n") + 1

			methods = append(methods, types.FunctionInfo{
				Name:       name,
				LineStart:  line,
				ReturnType: strings.TrimSpace(returnType),
				IsStatic:   isStatic,
			})
		}
	}

	return methods
}

func extractObjCClasses(source string, lines []string) []types.ClassInfo {
	var classes []types.ClassInfo

	// Match @interface declarations
	interfaceRegex := regexp.MustCompile(`(?m)^[\t ]*@interface\s+(\w+)(?:\s*:\s*(\w+))?(?:\s*<([^>]+)>)?`)

	matches := interfaceRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			extends := ""
			if len(match) >= 6 && match[4] != -1 && match[5] != -1 {
				extends = source[match[4]:match[5]]
			}

			var implements []string
			if len(match) >= 8 && match[6] != -1 && match[7] != -1 {
				protocols := source[match[6]:match[7]]
				parts := strings.Split(protocols, ",")
				for _, p := range parts {
					p = strings.TrimSpace(p)
					if p != "" {
						implements = append(implements, p)
					}
				}
			}

			classes = append(classes, types.ClassInfo{
				Name:       name,
				LineStart:  line,
				Extends:    extends,
				Implements: implements,
			})
		}
	}

	// Match @implementation declarations
	implRegex := regexp.MustCompile(`(?m)^[\t ]*@implementation\s+(\w+)`)
	matches = implRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			// Check if we already have this class from @interface
			found := false
			for _, c := range classes {
				if c.Name == name {
					found = true
					break
				}
			}

			if !found {
				classes = append(classes, types.ClassInfo{
					Name:      name,
					LineStart: line,
				})
			}
		}
	}

	// Match @protocol declarations
	protocolRegex := regexp.MustCompile(`(?m)^[\t ]*@protocol\s+(\w+)`)
	matches = protocolRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			name := source[match[2]:match[3]]
			line := strings.Count(source[:match[0]], "\n") + 1

			classes = append(classes, types.ClassInfo{
				Name:      name,
				LineStart: line,
			})
		}
	}

	return classes
}

func extractObjCImports(source string, lines []string) []types.ImportInfo {
	var imports []types.ImportInfo

	// #import statements
	importRegex := regexp.MustCompile(`(?m)^[\t ]*#import\s*[<"]([^>"]+)[>"]`)
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

	// #include statements
	includeRegex := regexp.MustCompile(`(?m)^[\t ]*#include\s*[<"]([^>"]+)[>"]`)
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
