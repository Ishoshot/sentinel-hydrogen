package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/csharp"
)

// AnalyzeCSharp analyzes C# source code and extracts semantic information
func AnalyzeCSharp(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(csharp.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "csharp",
			Errors:   []types.SyntaxError{{Message: "failed to parse C#"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "csharp",
		Functions: extractCSharpFunctions(root, source),
		Classes:   extractCSharpClasses(root, source),
		Imports:   extractCSharpImports(root, source),
		Calls:     extractCSharpCalls(root, source),
		Errors:    detectCSharpSyntaxErrors(root, source),
	}

	return result, nil
}

func extractCSharpFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"method_declaration", "local_function_statement"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		// Skip methods inside classes (they'll be extracted as class methods)
		parent := node.Parent()
		if parent != nil && (parent.Type() == "class_declaration" || parent.Type() == "declaration_list") {
			continue
		}

		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractCSharpParameters(node, source)
		returnType := extractCSharpReturnType(node, source)
		visibility := extractCSharpVisibility(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsStatic:   hasCSharpModifier(node, source, "static"),
			IsAsync:    hasCSharpModifier(node, source, "async"),
		})
	}

	return functions
}

func extractCSharpClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class_declaration", "struct_declaration", "record_declaration"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractCSharpExtends(node, source)
		implements := extractCSharpImplements(node, source)
		methods := extractCSharpMethods(node, source)
		properties := extractCSharpProperties(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Extends:    extends,
			Implements: implements,
			Methods:    methods,
			Properties: properties,
		})
	}

	// Also extract interfaces
	interfaceNodes := FindNodes(root, []string{"interface_declaration"})
	for _, node := range interfaceNodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractCSharpInterfaceMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	return classes
}

func extractCSharpImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"using_directive"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "qualified_name")
		if nameNode == nil {
			nameNode = FindChildByType(node, "identifier")
		}
		if nameNode == nil {
			continue
		}

		imports = append(imports, types.ImportInfo{
			Module: GetNodeText(nameNode, source),
			Line:   int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractCSharpCalls(root *sitter.Node, source []byte) []types.CallInfo {
	methodCalls := FindNodes(root, []string{"invocation_expression"})
	var calls []types.CallInfo

	for _, node := range methodCalls {
		// Get the expression being invoked
		exprNode := node.Child(0)
		if exprNode == nil {
			continue
		}

		callee := ""
		receiver := ""
		isMethodCall := false

		if exprNode.Type() == "member_access_expression" {
			// object.Method()
			nameNode := FindChildByType(exprNode, "identifier")
			if nameNode != nil {
				callee = GetNodeText(nameNode, source)
			}
			objNode := exprNode.Child(0)
			if objNode != nil {
				receiver = GetNodeText(objNode, source)
			}
			isMethodCall = true
		} else if exprNode.Type() == "identifier" {
			callee = GetNodeText(exprNode, source)
		}

		argsNode := FindChildByType(node, "argument_list")
		argCount := countCSharpArguments(argsNode)

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

func extractCSharpParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "parameter_list")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	paramNodes := FindNodes(paramsNode, []string{"parameter"})

	for _, paramNode := range paramNodes {
		typeNode := FindChildByType(paramNode, "predefined_type")
		if typeNode == nil {
			typeNode = FindChildByType(paramNode, "identifier")
		}
		if typeNode == nil {
			typeNode = FindChildByType(paramNode, "generic_name")
		}

		nameNode := paramNode.ChildByFieldName("name")
		if nameNode == nil {
			// Try to find identifier that's not the type
			for i := 0; i < int(paramNode.ChildCount()); i++ {
				child := paramNode.Child(i)
				if child != nil && child.Type() == "identifier" && child != typeNode {
					nameNode = child
					break
				}
			}
		}

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

func extractCSharpReturnType(node *sitter.Node, source []byte) string {
	// Return type is typically before the method name
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child == nil {
			continue
		}
		childType := child.Type()
		if childType == "predefined_type" || childType == "identifier" || childType == "generic_name" || childType == "array_type" || childType == "nullable_type" {
			// Make sure it's not the method name
			nextChild := node.Child(i + 1)
			if nextChild != nil && (nextChild.Type() == "identifier" || nextChild.Type() == "parameter_list") {
				return GetNodeText(child, source)
			}
		}
	}
	return ""
}

func extractCSharpVisibility(node *sitter.Node, source []byte) string {
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child == nil {
			continue
		}
		childType := child.Type()
		if childType == "modifier" {
			text := GetNodeText(child, source)
			if text == "public" || text == "private" || text == "protected" || text == "internal" {
				return text
			}
		}
	}
	return ""
}

func hasCSharpModifier(node *sitter.Node, source []byte, modifier string) bool {
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil && child.Type() == "modifier" {
			if GetNodeText(child, source) == modifier {
				return true
			}
		}
	}
	return false
}

func extractCSharpExtends(node *sitter.Node, source []byte) string {
	baseListNode := FindChildByType(node, "base_list")
	if baseListNode == nil {
		return ""
	}
	// First type in base list is typically the base class
	typeNode := FindChildByType(baseListNode, "identifier")
	if typeNode == nil {
		typeNode = FindChildByType(baseListNode, "generic_name")
	}
	if typeNode != nil {
		return GetNodeText(typeNode, source)
	}
	return ""
}

func extractCSharpImplements(node *sitter.Node, source []byte) []string {
	baseListNode := FindChildByType(node, "base_list")
	if baseListNode == nil {
		return []string{}
	}

	var implements []string
	typeNodes := FindNodes(baseListNode, []string{"identifier", "generic_name"})
	// Skip first one (it's the base class)
	for i, typeNode := range typeNodes {
		if i == 0 {
			continue
		}
		implements = append(implements, GetNodeText(typeNode, source))
	}
	return implements
}

func extractCSharpMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "declaration_list")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"method_declaration"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractCSharpParameters(methodNode, source)
		returnType := extractCSharpReturnType(methodNode, source)
		visibility := extractCSharpVisibility(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsStatic:   hasCSharpModifier(methodNode, source, "static"),
			IsAsync:    hasCSharpModifier(methodNode, source, "async"),
		})
	}

	return methods
}

func extractCSharpInterfaceMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "declaration_list")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"method_declaration"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractCSharpParameters(methodNode, source)
		returnType := extractCSharpReturnType(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: "public",
		})
	}

	return methods
}

func extractCSharpProperties(node *sitter.Node, source []byte) []types.PropertyInfo {
	bodyNode := FindChildByType(node, "declaration_list")
	if bodyNode == nil {
		return []types.PropertyInfo{}
	}

	propNodes := FindNodes(bodyNode, []string{"property_declaration", "field_declaration"})
	var properties []types.PropertyInfo

	for _, propNode := range propNodes {
		visibility := extractCSharpVisibility(propNode, source)

		// Get type
		typeNode := FindChildByType(propNode, "predefined_type")
		if typeNode == nil {
			typeNode = FindChildByType(propNode, "identifier")
		}
		if typeNode == nil {
			typeNode = FindChildByType(propNode, "generic_name")
		}

		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		// Get name
		if propNode.Type() == "property_declaration" {
			nameNode := propNode.ChildByFieldName("name")
			if nameNode == nil {
				// Find identifier that's not the type
				for i := 0; i < int(propNode.ChildCount()); i++ {
					child := propNode.Child(i)
					if child != nil && child.Type() == "identifier" && child != typeNode {
						nameNode = child
						break
					}
				}
			}
			if nameNode != nil {
				properties = append(properties, types.PropertyInfo{
					Name:       GetNodeText(nameNode, source),
					Type:       typeName,
					Visibility: visibility,
				})
			}
		} else {
			// field_declaration
			declarators := FindNodes(propNode, []string{"variable_declarator"})
			for _, decl := range declarators {
				nameNode := FindChildByType(decl, "identifier")
				if nameNode != nil {
					properties = append(properties, types.PropertyInfo{
						Name:       GetNodeText(nameNode, source),
						Type:       typeName,
						Visibility: visibility,
					})
				}
			}
		}
	}

	return properties
}

func countCSharpArguments(argsNode *sitter.Node) int {
	if argsNode == nil {
		return 0
	}
	count := 0
	for i := 0; i < int(argsNode.ChildCount()); i++ {
		child := argsNode.Child(i)
		if child != nil && child.Type() == "argument" {
			count++
		}
	}
	return count
}

func detectCSharpSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
