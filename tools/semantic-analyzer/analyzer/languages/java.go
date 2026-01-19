package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/java"
)

// AnalyzeJava analyzes Java source code and extracts semantic information
func AnalyzeJava(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(java.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "java",
			Errors:   []types.SyntaxError{{Message: "failed to parse Java"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "java",
		Functions: extractJavaFunctions(root, source),
		Classes:   extractJavaClasses(root, source),
		Imports:   extractJavaImports(root, source),
		Calls:     extractJavaCalls(root, source),
		Errors:    detectJavaSyntaxErrors(root, source),
	}

	return result, nil
}

func extractJavaFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"method_declaration"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		// Skip methods inside classes (they'll be extracted as class methods)
		parent := node.Parent()
		if parent != nil && parent.Type() == "class_body" {
			continue
		}

		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractJavaParameters(node, source)
		returnType := extractJavaReturnType(node, source)
		visibility := extractJavaVisibility(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsStatic:   hasJavaModifier(node, source, "static"),
		})
	}

	return functions
}

func extractJavaClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class_declaration"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractJavaExtends(node, source)
		implements := extractJavaImplements(node, source)
		methods := extractJavaMethods(node, source)
		properties := extractJavaFields(node, source)

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
		methods := extractJavaInterfaceMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	return classes
}

func extractJavaImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"import_declaration"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		// Get the full import text
		text := GetNodeText(node, source)
		// Remove "import " and ";"
		if len(text) > 7 {
			module := text[7:]
			if len(module) > 0 && module[len(module)-1] == ';' {
				module = module[:len(module)-1]
			}

			imports = append(imports, types.ImportInfo{
				Module: module,
				Line:   int(node.StartPoint().Row) + 1,
			})
		}
	}

	return imports
}

func extractJavaCalls(root *sitter.Node, source []byte) []types.CallInfo {
	methodCalls := FindNodes(root, []string{"method_invocation"})
	var calls []types.CallInfo

	for _, node := range methodCalls {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		callee := GetNodeText(nameNode, source)
		argsNode := FindChildByType(node, "argument_list")
		argCount := countJavaArguments(argsNode)

		// Check for receiver (object.method())
		objectNode := FindChildByType(node, "field_access")
		receiver := ""
		isMethodCall := false
		if objectNode != nil {
			receiver = GetNodeText(objectNode, source)
			isMethodCall = true
		}

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

func extractJavaParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "formal_parameters")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	paramNodes := FindNodes(paramsNode, []string{"formal_parameter"})

	for _, paramNode := range paramNodes {
		typeNode := paramNode.Child(0)
		nameNode := FindChildByType(paramNode, "identifier")

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

func extractJavaReturnType(node *sitter.Node, source []byte) string {
	// Return type is typically before the method name
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child == nil {
			continue
		}
		childType := child.Type()
		if childType == "type_identifier" || childType == "void_type" || childType == "generic_type" || childType == "array_type" {
			return GetNodeText(child, source)
		}
	}
	return ""
}

func extractJavaVisibility(node *sitter.Node, source []byte) string {
	modifiersNode := FindChildByType(node, "modifiers")
	if modifiersNode == nil {
		return ""
	}

	modifiers := GetNodeText(modifiersNode, source)
	if contains(modifiers, "public") {
		return "public"
	} else if contains(modifiers, "private") {
		return "private"
	} else if contains(modifiers, "protected") {
		return "protected"
	}
	return ""
}

func hasJavaModifier(node *sitter.Node, source []byte, modifier string) bool {
	modifiersNode := FindChildByType(node, "modifiers")
	if modifiersNode == nil {
		return false
	}
	modifiers := GetNodeText(modifiersNode, source)
	return contains(modifiers, modifier)
}

func extractJavaExtends(node *sitter.Node, source []byte) string {
	superclassNode := FindChildByType(node, "superclass")
	if superclassNode == nil {
		return ""
	}
	typeNode := FindChildByType(superclassNode, "type_identifier")
	if typeNode == nil {
		return ""
	}
	return GetNodeText(typeNode, source)
}

func extractJavaImplements(node *sitter.Node, source []byte) []string {
	interfacesNode := FindChildByType(node, "super_interfaces")
	if interfacesNode == nil {
		return []string{}
	}

	var implements []string
	typeNodes := FindNodes(interfacesNode, []string{"type_identifier"})
	for _, typeNode := range typeNodes {
		implements = append(implements, GetNodeText(typeNode, source))
	}
	return implements
}

func extractJavaMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "class_body")
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
		params := extractJavaParameters(methodNode, source)
		returnType := extractJavaReturnType(methodNode, source)
		visibility := extractJavaVisibility(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsStatic:   hasJavaModifier(methodNode, source, "static"),
		})
	}

	return methods
}

func extractJavaInterfaceMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "interface_body")
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
		params := extractJavaParameters(methodNode, source)
		returnType := extractJavaReturnType(methodNode, source)

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

func extractJavaFields(node *sitter.Node, source []byte) []types.PropertyInfo {
	bodyNode := FindChildByType(node, "class_body")
	if bodyNode == nil {
		return []types.PropertyInfo{}
	}

	fieldNodes := FindNodes(bodyNode, []string{"field_declaration"})
	var properties []types.PropertyInfo

	for _, fieldNode := range fieldNodes {
		visibility := extractJavaVisibility(fieldNode, source)

		// Get type
		typeNode := fieldNode.Child(0)
		if typeNode != nil && typeNode.Type() == "modifiers" {
			typeNode = fieldNode.Child(1)
		}
		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		// Get variable declarators
		declarators := FindNodes(fieldNode, []string{"variable_declarator"})
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

	return properties
}

func countJavaArguments(argsNode *sitter.Node) int {
	if argsNode == nil {
		return 0
	}
	count := 0
	for i := 0; i < int(argsNode.ChildCount()); i++ {
		child := argsNode.Child(i)
		if child != nil && child.Type() != "," && child.Type() != "(" && child.Type() != ")" {
			count++
		}
	}
	return count
}

func contains(s, substr string) bool {
	return len(s) >= len(substr) && (s == substr || len(s) > 0 && containsHelper(s, substr))
}

func containsHelper(s, substr string) bool {
	for i := 0; i <= len(s)-len(substr); i++ {
		if s[i:i+len(substr)] == substr {
			return true
		}
	}
	return false
}

func detectJavaSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
