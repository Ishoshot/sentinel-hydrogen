package languages

import (
	"strings"

	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/typescript/typescript"
	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeTypeScript analyzes TypeScript/TSX source code
func AnalyzeTypeScript(source []byte, language string) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(typescript.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: language,
			Errors:   []types.SyntaxError{{Message: "failed to parse TypeScript"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  language,
		Functions: extractTSFunctions(root, source),
		Classes:   extractTSClasses(root, source),
		Imports:   extractTSImports(root, source),
		Exports:   extractTSExports(root, source),
		Calls:     extractTSCalls(root, source),
		Errors:    detectTSSyntaxErrors(root),
	}

	return result, nil
}

func extractTSFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_declaration", "arrow_function", "function"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		var name string
		var params []types.ParameterInfo
		var returnType string
		isAsync := false

		if node.Type() == "function_declaration" || node.Type() == "function" {
			nameNode := FindChildByType(node, "identifier")
			if nameNode != nil {
				name = GetNodeText(nameNode, source)
			}

			paramsNode := FindChildByType(node, "formal_parameters")
			params = extractTSParameters(paramsNode, source)

			typeAnnotation := FindChildByType(node, "type_annotation")
			if typeAnnotation != nil {
				returnType = GetNodeText(typeAnnotation, source)
				returnType = strings.TrimPrefix(returnType, ":")
				returnType = strings.TrimSpace(returnType)
			}

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
				firstChild := node.Child(0)
				if firstChild != nil && firstChild.Type() == "identifier" {
					params = []types.ParameterInfo{{Name: GetNodeText(firstChild, source)}}
				}
			} else {
				params = extractTSParameters(paramsNode, source)
			}

			typeAnnotation := FindChildByType(node, "type_annotation")
			if typeAnnotation != nil {
				returnType = GetNodeText(typeAnnotation, source)
				returnType = strings.TrimPrefix(returnType, ":")
				returnType = strings.TrimSpace(returnType)
			}
		}

		if name != "" || node.Type() == "arrow_function" {
			functions = append(functions, types.FunctionInfo{
				Name:       name,
				LineStart:  int(node.StartPoint().Row) + 1,
				LineEnd:    int(node.EndPoint().Row) + 1,
				Parameters: params,
				ReturnType: returnType,
				IsAsync:    isAsync,
			})
		}
	}

	return functions
}

func extractTSClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class_declaration"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			nameNode = FindChildByType(node, "identifier")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractTSExtends(node, source)
		implements := extractTSImplements(node, source)
		methods := extractTSMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Extends:    extends,
			Implements: implements,
			Methods:    methods,
			Properties: []types.PropertyInfo{},
		})
	}

	return classes
}

func extractTSImports(root *sitter.Node, source []byte) []types.ImportInfo {
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
			defaultNode := FindChildByType(clauseNode, "identifier")
			if defaultNode != nil {
				isDefault = true
				symbols = append(symbols, GetNodeText(defaultNode, source))
			}

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

func extractTSExports(root *sitter.Node, source []byte) []types.ExportInfo {
	nodes := FindNodes(root, []string{"export_statement"})
	var exports []types.ExportInfo

	for _, node := range nodes {
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

			if child.Type() == "identifier" || child.Type() == "type_identifier" {
				exports = append(exports, types.ExportInfo{
					Name: GetNodeText(child, source),
					Line: int(node.StartPoint().Row) + 1,
				})
			}
		}
	}

	return exports
}

func extractTSCalls(root *sitter.Node, source []byte) []types.CallInfo {
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

func extractTSParameters(paramsNode *sitter.Node, source []byte) []types.ParameterInfo {
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil {
			continue
		}

		var name string
		var typeName string

		switch child.Type() {
		case "identifier":
			name = GetNodeText(child, source)
		case "required_parameter", "optional_parameter":
			patternNode := FindChildByType(child, "identifier")
			if patternNode != nil {
				name = GetNodeText(patternNode, source)
			}
			typeAnnotation := FindChildByType(child, "type_annotation")
			if typeAnnotation != nil {
				typeName = GetNodeText(typeAnnotation, source)
				typeName = strings.TrimPrefix(typeName, ":")
				typeName = strings.TrimSpace(typeName)
			}
		}

		if name != "" {
			params = append(params, types.ParameterInfo{
				Name: name,
				Type: typeName,
			})
		}
	}

	return params
}

func extractTSExtends(node *sitter.Node, source []byte) string {
	heritageNode := FindChildByType(node, "class_heritage")
	if heritageNode == nil {
		return ""
	}
	extendsClause := FindChildByType(heritageNode, "extends_clause")
	if extendsClause == nil {
		return ""
	}
	typeNode := FindChildByType(extendsClause, "type_identifier")
	if typeNode == nil {
		typeNode = FindChildByType(extendsClause, "identifier")
	}
	if typeNode == nil {
		return ""
	}
	return GetNodeText(typeNode, source)
}

func extractTSImplements(node *sitter.Node, source []byte) []string {
	heritageNode := FindChildByType(node, "class_heritage")
	if heritageNode == nil {
		return []string{}
	}

	implementsClause := FindChildByType(heritageNode, "implements_clause")
	if implementsClause == nil {
		return []string{}
	}

	var implements []string
	for i := 0; i < int(implementsClause.ChildCount()); i++ {
		child := implementsClause.Child(i)
		if child != nil && (child.Type() == "type_identifier" || child.Type() == "identifier") {
			implements = append(implements, GetNodeText(child, source))
		}
	}
	return implements
}

func extractTSMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
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
		params := extractTSParameters(paramsNode, source)

		typeAnnotation := FindChildByType(methodNode, "type_annotation")
		returnType := ""
		if typeAnnotation != nil {
			returnType = GetNodeText(typeAnnotation, source)
			returnType = strings.TrimPrefix(returnType, ":")
			returnType = strings.TrimSpace(returnType)
		}

		isAsync := false
		isStatic := false
		visibility := ""
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
				if text == "public" || text == "private" || text == "protected" {
					visibility = text
				}
			}
		}

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsAsync:    isAsync,
			IsStatic:   isStatic,
		})
	}

	return methods
}

func detectTSSyntaxErrors(root *sitter.Node) []types.SyntaxError {
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
