package languages

import (
	"strings"

	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/javascript"
	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeJavaScript analyzes JavaScript/JSX source code
func AnalyzeJavaScript(source []byte, language string) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(javascript.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: language,
			Errors:   []types.SyntaxError{{Message: "failed to parse JavaScript"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  language,
		Functions: extractJSFunctions(root, source),
		Classes:   extractJSClasses(root, source),
		Imports:   extractJSImports(root, source),
		Exports:   extractJSExports(root, source),
		Calls:     extractJSCalls(root, source),
		Errors:    detectJSSyntaxErrors(root),
	}

	return result, nil
}

func extractJSFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_declaration", "arrow_function", "function"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		var name string
		var params []types.ParameterInfo
		isAsync := false

		// Check for async modifier
		if node.Type() == "function_declaration" || node.Type() == "function" {
			nameNode := FindChildByType(node, "identifier")
			if nameNode != nil {
				name = GetNodeText(nameNode, source)
			}

			paramsNode := FindChildByType(node, "formal_parameters")
			params = extractJSParameters(paramsNode, source)

			// Check for async keyword
			for i := 0; i < int(node.ChildCount()); i++ {
				child := node.Child(i)
				if child != nil && GetNodeText(child, source) == "async" {
					isAsync = true
					break
				}
			}
		} else if node.Type() == "arrow_function" {
			paramsNode := FindChildByType(node, "formal_parameters")
			if paramsNode == nil {
				// Single parameter without parentheses
				firstChild := node.Child(0)
				if firstChild != nil && firstChild.Type() == "identifier" {
					params = []types.ParameterInfo{{Name: GetNodeText(firstChild, source)}}
				}
			} else {
				params = extractJSParameters(paramsNode, source)
			}
		}

		if name != "" || node.Type() == "arrow_function" {
			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  int(node.StartPoint().Row) + 1,
				LineEnd:    int(node.EndPoint().Row) + 1,
				Parameters: params,
				IsAsync:    isAsync,
			})
		}
	}

	return functions
}

func extractJSClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class_declaration"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractJSExtends(node, source)
		methods := extractJSMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Extends:    extends,
			Implements: []string{},
			Methods:    methods,
			Properties: []types.PropertyInfo{},
		})
	}

	return classes
}

func extractJSImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"import_statement"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		sourceNode := FindChildByType(node, "string")
		if sourceNode == nil {
			continue
		}

		module := strings.Trim(GetNodeText(sourceNode, source), "\"'`")
		symbols := []string{}
		isDefault := false

		clauseNode := FindChildByType(node, "import_clause")
		if clauseNode != nil {
			// Check for default import
			defaultNode := FindChildByType(clauseNode, "identifier")
			if defaultNode != nil {
				isDefault = true
				symbols = append(symbols, GetNodeText(defaultNode, source))
			}

			// Check for named imports
			specifierNode := FindChildByType(clauseNode, "named_imports")
			if specifierNode != nil {
				for i := 0; i < int(specifierNode.ChildCount()); i++ {
					child := specifierNode.Child(i)
					if child != nil && child.Type() == "import_specifier" {
						nameNode := FindChildByType(child, "identifier")
						if nameNode != nil {
							symbols = append(symbols, GetNodeText(nameNode, source))
						}
					}
				}
			}
		}

		imports = append(imports, types.ImportInfo{
			Module:    module,
			Symbols:   symbols,
			Line:      int(node.StartPoint().Row) + 1,
			IsDefault: isDefault,
		})
	}

	return imports
}

func extractJSExports(root *sitter.Node, source []byte) []types.ExportInfo {
	nodes := FindNodes(root, []string{"export_statement"})
	var exports []types.ExportInfo

	for _, node := range nodes {
		// Check for default export
		for i := 0; i < int(node.ChildCount()); i++ {
			child := node.Child(i)
			if child == nil {
				continue
			}

			if GetNodeText(child, source) == "default" {
				exports = append(exports, types.ExportInfo{
					Name: "default",
					Line: int(node.StartPoint().Row) + 1,
				})
				break
			}

			// Named exports
			if child.Type() == "identifier" {
				exports = append(exports, types.ExportInfo{
					Name: GetNodeText(child, source),
					Line: int(node.StartPoint().Row) + 1,
				})
			}
		}
	}

	return exports
}

func extractJSCalls(root *sitter.Node, source []byte) []types.CallInfo {
	nodes := FindNodes(root, []string{"call_expression"})
	var calls []types.CallInfo

	for _, node := range nodes {
		functionNode := node.Child(0)
		if functionNode == nil {
			continue
		}

		isMethodCall := functionNode.Type() == "member_expression"
		callee := ""
		receiver := ""

		if isMethodCall {
			propertyNode := FindChildByType(functionNode, "property_identifier")
			if propertyNode != nil {
				callee = GetNodeText(propertyNode, source)
			}
			objectNode := functionNode.Child(0)
			if objectNode != nil {
				receiver = GetNodeText(objectNode, source)
			}
		} else {
			callee = GetNodeText(functionNode, source)
		}

		argsNode := FindChildByType(node, "arguments")
		argCount := CountArguments(argsNode)

		calls = append(calls, types.CallInfo{
			Callee:         callee,
			Line:           int(node.StartPoint().Row) + 1,
			ArgumentsCount: argCount,
			IsMethodCall:   isMethodCall,
			Receiver:       receiver,
		})
	}

	return calls
}

func extractJSParameters(paramsNode *sitter.Node, source []byte) []types.ParameterInfo {
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil {
			continue
		}

		switch child.Type() {
		case "identifier":
			params = append(params, types.ParameterInfo{
				Name: GetNodeText(child, source),
			})
		case "required_parameter", "optional_parameter":
			patternNode := FindChildByType(child, "identifier")
			if patternNode != nil {
				params = append(params, types.ParameterInfo{
					Name: GetNodeText(patternNode, source),
				})
			}
		}
	}

	return params
}

func extractJSExtends(node *sitter.Node, source []byte) string {
	heritageNode := FindChildByType(node, "class_heritage")
	if heritageNode == nil {
		return ""
	}
	extendsNode := FindChildByType(heritageNode, "identifier")
	if extendsNode == nil {
		return ""
	}
	return GetNodeText(extendsNode, source)
}

func extractJSMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "class_body")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindChildrenByType(bodyNode, "method_definition")
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "property_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		paramsNode := FindChildByType(methodNode, "formal_parameters")
		params := extractJSParameters(paramsNode, source)

		isAsync := false
		isStatic := false
		for i := 0; i < int(methodNode.ChildCount()); i++ {
			child := methodNode.Child(i)
			if child != nil {
				text := GetNodeText(child, source)
				if text == "async" {
					isAsync = true
				}
				if text == "static" {
					isStatic = true
				}
			}
		}

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			IsAsync:    isAsync,
			IsStatic:   isStatic,
		})
	}

	return methods
}

func detectJSSyntaxErrors(root *sitter.Node) []types.SyntaxError {
	var errors []types.SyntaxError

	WalkTree(root, func(node *sitter.Node) {
		if node.Type() == "ERROR" || node.IsMissing() {
			errors = append(errors, types.SyntaxError{
				Line:    int(node.StartPoint().Row) + 1,
				Column:  int(node.StartPoint().Column) + 1,
				Message: "Syntax error detected",
			})
		}
	})

	return errors
}
