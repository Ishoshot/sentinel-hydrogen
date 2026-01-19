package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/swift"
)

// AnalyzeSwift analyzes Swift source code and extracts semantic information
func AnalyzeSwift(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(swift.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "swift",
			Errors:   []types.SyntaxError{{Message: "failed to parse Swift"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "swift",
		Functions: extractSwiftFunctions(root, source),
		Classes:   extractSwiftClasses(root, source),
		Imports:   extractSwiftImports(root, source),
		Calls:     extractSwiftCalls(root, source),
		Errors:    detectSwiftSyntaxErrors(root, source),
	}

	return result, nil
}

func extractSwiftFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_declaration"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		// Skip methods inside classes/structs
		parent := node.Parent()
		if parent != nil && (parent.Type() == "class_body" || parent.Type() == "struct_body") {
			continue
		}

		nameNode := FindChildByType(node, "simple_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractSwiftParameters(node, source)
		returnType := extractSwiftReturnType(node, source)
		visibility := extractSwiftVisibility(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsAsync:    hasSwiftModifier(node, source, "async"),
			IsStatic:   hasSwiftModifier(node, source, "static"),
		})
	}

	return functions
}

func extractSwiftClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	var classes []types.ClassInfo

	// Classes
	classNodes := FindNodes(root, []string{"class_declaration"})
	for _, node := range classNodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			nameNode = FindChildByType(node, "simple_identifier")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractSwiftExtends(node, source)
		methods := extractSwiftMethods(node, source)
		properties := extractSwiftProperties(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Extends:    extends,
			Methods:    methods,
			Properties: properties,
		})
	}

	// Structs
	structNodes := FindNodes(root, []string{"struct_declaration"})
	for _, node := range structNodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			nameNode = FindChildByType(node, "simple_identifier")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractSwiftMethods(node, source)
		properties := extractSwiftProperties(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Methods:    methods,
			Properties: properties,
		})
	}

	// Protocols
	protocolNodes := FindNodes(root, []string{"protocol_declaration"})
	for _, node := range protocolNodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			nameNode = FindChildByType(node, "simple_identifier")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractSwiftProtocolMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	return classes
}

func extractSwiftImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"import_declaration"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		identNode := FindChildByType(node, "identifier")
		if identNode == nil {
			identNode = FindChildByType(node, "simple_identifier")
		}
		if identNode == nil {
			continue
		}

		imports = append(imports, types.ImportInfo{
			Module: GetNodeText(identNode, source),
			Line:   int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractSwiftCalls(root *sitter.Node, source []byte) []types.CallInfo {
	callNodes := FindNodes(root, []string{"call_expression"})
	var calls []types.CallInfo

	for _, node := range callNodes {
		exprNode := node.Child(0)
		if exprNode == nil {
			continue
		}

		callee := ""
		receiver := ""
		isMethodCall := false

		if exprNode.Type() == "navigation_expression" {
			// object.method()
			suffixNode := FindChildByType(exprNode, "navigation_suffix")
			if suffixNode != nil {
				nameNode := FindChildByType(suffixNode, "simple_identifier")
				if nameNode != nil {
					callee = GetNodeText(nameNode, source)
				}
			}
			objNode := exprNode.Child(0)
			if objNode != nil {
				receiver = GetNodeText(objNode, source)
			}
			isMethodCall = true
		} else if exprNode.Type() == "simple_identifier" {
			callee = GetNodeText(exprNode, source)
		}

		argsNode := FindChildByType(node, "call_suffix")
		argCount := countSwiftArguments(argsNode)

		if callee != "" {
			calls = append(calls, types.CallInfo{
				Callee:         callee,
				Line:           int(node.StartPoint().Row) + 1,
				ArgumentsCount: argCount,
				IsMethodCall:   isMethodCall,
				Receiver:       receiver,
			})
		}
	}

	return calls
}

func extractSwiftParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "function_value_parameters")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	paramNodes := FindNodes(paramsNode, []string{"function_value_parameter"})

	for _, paramNode := range paramNodes {
		nameNode := FindChildByType(paramNode, "simple_identifier")
		typeNode := FindChildByType(paramNode, "type_annotation")

		if nameNode == nil {
			continue
		}

		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		params = append(params, types.ParameterInfo{
			Name: GetNodeText(nameNode, source),
			Type: typeName,
		})
	}

	return params
}

func extractSwiftReturnType(node *sitter.Node, source []byte) string {
	returnTypeNode := FindChildByType(node, "function_type")
	if returnTypeNode == nil {
		// Try to find arrow and type after it
		for i := 0; i < int(node.ChildCount()); i++ {
			child := node.Child(i)
			if child != nil && child.Type() == "->" {
				nextChild := node.Child(i + 1)
				if nextChild != nil {
					return GetNodeText(nextChild, source)
				}
			}
		}
	}
	if returnTypeNode != nil {
		return GetNodeText(returnTypeNode, source)
	}
	return ""
}

func extractSwiftVisibility(node *sitter.Node, source []byte) string {
	modifiersNode := FindChildByType(node, "modifiers")
	if modifiersNode == nil {
		return ""
	}

	for i := 0; i < int(modifiersNode.ChildCount()); i++ {
		child := modifiersNode.Child(i)
		if child == nil {
			continue
		}
		text := GetNodeText(child, source)
		if text == "public" || text == "private" || text == "internal" || text == "fileprivate" || text == "open" {
			return text
		}
	}
	return ""
}

func hasSwiftModifier(node *sitter.Node, source []byte, modifier string) bool {
	modifiersNode := FindChildByType(node, "modifiers")
	if modifiersNode == nil {
		return false
	}

	for i := 0; i < int(modifiersNode.ChildCount()); i++ {
		child := modifiersNode.Child(i)
		if child != nil && GetNodeText(child, source) == modifier {
			return true
		}
	}
	return false
}

func extractSwiftExtends(node *sitter.Node, source []byte) string {
	inheritanceNode := FindChildByType(node, "inheritance_specifier")
	if inheritanceNode == nil {
		return ""
	}
	typeNode := FindChildByType(inheritanceNode, "type_identifier")
	if typeNode == nil {
		return ""
	}
	return GetNodeText(typeNode, source)
}

func extractSwiftMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "class_body")
	if bodyNode == nil {
		bodyNode = FindChildByType(node, "struct_body")
	}
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"function_declaration"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "simple_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractSwiftParameters(methodNode, source)
		returnType := extractSwiftReturnType(methodNode, source)
		visibility := extractSwiftVisibility(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsAsync:    hasSwiftModifier(methodNode, source, "async"),
			IsStatic:   hasSwiftModifier(methodNode, source, "static"),
		})
	}

	return methods
}

func extractSwiftProtocolMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "protocol_body")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"protocol_function_declaration"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "simple_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractSwiftParameters(methodNode, source)
		returnType := extractSwiftReturnType(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
		})
	}

	return methods
}

func extractSwiftProperties(node *sitter.Node, source []byte) []types.PropertyInfo {
	bodyNode := FindChildByType(node, "class_body")
	if bodyNode == nil {
		bodyNode = FindChildByType(node, "struct_body")
	}
	if bodyNode == nil {
		return []types.PropertyInfo{}
	}

	propNodes := FindNodes(bodyNode, []string{"property_declaration"})
	var properties []types.PropertyInfo

	for _, propNode := range propNodes {
		bindingNode := FindChildByType(propNode, "pattern")
		if bindingNode == nil {
			continue
		}

		nameNode := FindChildByType(bindingNode, "simple_identifier")
		if nameNode == nil {
			continue
		}

		visibility := extractSwiftVisibility(propNode, source)
		typeNode := FindChildByType(propNode, "type_annotation")
		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		properties = append(properties, types.PropertyInfo{
			Name:       GetNodeText(nameNode, source),
			Type:       typeName,
			Visibility: visibility,
		})
	}

	return properties
}

func countSwiftArguments(argsNode *sitter.Node) int {
	if argsNode == nil {
		return 0
	}
	valueArgsNode := FindChildByType(argsNode, "value_arguments")
	if valueArgsNode == nil {
		return 0
	}
	count := 0
	for i := 0; i < int(valueArgsNode.ChildCount()); i++ {
		child := valueArgsNode.Child(i)
		if child != nil && child.Type() == "value_argument" {
			count++
		}
	}
	return count
}

func detectSwiftSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
