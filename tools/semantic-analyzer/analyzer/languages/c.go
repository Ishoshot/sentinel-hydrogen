package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/c"
	"github.com/smacker/go-tree-sitter/cpp"
)

// AnalyzeC analyzes C source code and extracts semantic information
func AnalyzeC(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(c.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "c",
			Errors:   []types.SyntaxError{{Message: "failed to parse C"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "c",
		Functions: extractCFunctions(root, source),
		Classes:   extractCStructs(root, source),
		Imports:   extractCIncludes(root, source),
		Calls:     extractCCalls(root, source),
		Errors:    detectCSyntaxErrors(root, source),
	}

	return result, nil
}

// AnalyzeCPP analyzes C++ source code and extracts semantic information
func AnalyzeCPP(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(cpp.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "cpp",
			Errors:   []types.SyntaxError{{Message: "failed to parse C++"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "cpp",
		Functions: extractCPPFunctions(root, source),
		Classes:   extractCPPClasses(root, source),
		Imports:   extractCIncludes(root, source),
		Calls:     extractCCalls(root, source),
		Errors:    detectCSyntaxErrors(root, source),
	}

	return result, nil
}

func extractCFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_definition"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		declaratorNode := FindChildByType(node, "function_declarator")
		if declaratorNode == nil {
			continue
		}

		nameNode := FindChildByType(declaratorNode, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractCParameters(declaratorNode, source)
		returnType := extractCReturnType(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
		})
	}

	return functions
}

func extractCPPFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_definition"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		// Skip methods inside classes
		parent := node.Parent()
		if parent != nil && parent.Type() == "class_specifier" {
			continue
		}

		declaratorNode := FindChildByType(node, "function_declarator")
		if declaratorNode == nil {
			continue
		}

		// Get name - might be qualified (ClassName::methodName)
		nameNode := FindChildByType(declaratorNode, "identifier")
		if nameNode == nil {
			nameNode = FindChildByType(declaratorNode, "qualified_identifier")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractCParameters(declaratorNode, source)
		returnType := extractCReturnType(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
		})
	}

	return functions
}

func extractCStructs(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"struct_specifier"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		fields := extractCStructFields(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Properties: fields,
		})
	}

	return classes
}

func extractCPPClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	var classes []types.ClassInfo

	// Classes
	classNodes := FindNodes(root, []string{"class_specifier"})
	for _, node := range classNodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractCPPExtends(node, source)
		methods := extractCPPMethods(node, source)
		fields := extractCPPFields(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Extends:    extends,
			Methods:    methods,
			Properties: fields,
		})
	}

	// Structs
	structNodes := FindNodes(root, []string{"struct_specifier"})
	for _, node := range structNodes {
		nameNode := FindChildByType(node, "type_identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractCPPMethods(node, source)
		fields := extractCPPFields(node, source)

		classes = append(classes, types.ClassInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Methods:    methods,
			Properties: fields,
		})
	}

	return classes
}

func extractCIncludes(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"preproc_include"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		pathNode := FindChildByType(node, "string_literal")
		if pathNode == nil {
			pathNode = FindChildByType(node, "system_lib_string")
		}
		if pathNode == nil {
			continue
		}

		module := GetNodeText(pathNode, source)
		// Remove quotes/brackets
		if len(module) >= 2 {
			module = module[1 : len(module)-1]
		}

		imports = append(imports, types.ImportInfo{
			Module: module,
			Line:   int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractCCalls(root *sitter.Node, source []byte) []types.CallInfo {
	callNodes := FindNodes(root, []string{"call_expression"})
	var calls []types.CallInfo

	for _, node := range callNodes {
		funcNode := node.Child(0)
		if funcNode == nil {
			continue
		}

		callee := ""
		receiver := ""
		isMethodCall := false

		if funcNode.Type() == "field_expression" {
			// obj.method() or obj->method()
			fieldNode := FindChildByType(funcNode, "field_identifier")
			if fieldNode != nil {
				callee = GetNodeText(fieldNode, source)
			}
			argNode := funcNode.Child(0)
			if argNode != nil {
				receiver = GetNodeText(argNode, source)
			}
			isMethodCall = true
		} else if funcNode.Type() == "identifier" {
			callee = GetNodeText(funcNode, source)
		}

		argsNode := FindChildByType(node, "argument_list")
		argCount := countCArguments(argsNode)

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

func extractCParameters(declaratorNode *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(declaratorNode, "parameter_list")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	paramNodes := FindNodes(paramsNode, []string{"parameter_declaration"})

	for _, paramNode := range paramNodes {
		declaratorNode := FindChildByType(paramNode, "identifier")
		if declaratorNode == nil {
			declaratorNode = FindChildByType(paramNode, "pointer_declarator")
			if declaratorNode != nil {
				declaratorNode = FindChildByType(declaratorNode, "identifier")
			}
		}

		typeName := ""
		// Type is usually the first child
		if paramNode.ChildCount() > 0 {
			typeChild := paramNode.Child(0)
			if typeChild != nil && typeChild.Type() != "identifier" {
				typeName = GetNodeText(typeChild, source)
			}
		}

		name := ""
		if declaratorNode != nil {
			name = GetNodeText(declaratorNode, source)
		}

		if name != "" || typeName != "" {
			params = append(params, types.ParameterInfo{
				Name: name,
				Type: typeName,
			})
		}
	}

	return params
}

func extractCReturnType(node *sitter.Node, source []byte) string {
	// Return type is usually the first child before the declarator
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child == nil {
			continue
		}
		childType := child.Type()
		if childType == "primitive_type" || childType == "type_identifier" || childType == "sized_type_specifier" {
			return GetNodeText(child, source)
		}
	}
	return ""
}

func extractCStructFields(node *sitter.Node, source []byte) []types.PropertyInfo {
	bodyNode := FindChildByType(node, "field_declaration_list")
	if bodyNode == nil {
		return []types.PropertyInfo{}
	}

	fieldNodes := FindNodes(bodyNode, []string{"field_declaration"})
	var properties []types.PropertyInfo

	for _, fieldNode := range fieldNodes {
		// Get type
		typeName := ""
		for i := 0; i < int(fieldNode.ChildCount()); i++ {
			child := fieldNode.Child(i)
			if child != nil {
				childType := child.Type()
				if childType == "primitive_type" || childType == "type_identifier" {
					typeName = GetNodeText(child, source)
					break
				}
			}
		}

		// Get names (there can be multiple: int a, b, c;)
		declarators := FindNodes(fieldNode, []string{"field_identifier"})
		for _, decl := range declarators {
			properties = append(properties, types.PropertyInfo{
				Name: GetNodeText(decl, source),
				Type: typeName,
			})
		}
	}

	return properties
}

func extractCPPExtends(node *sitter.Node, source []byte) string {
	baseClauseNode := FindChildByType(node, "base_class_clause")
	if baseClauseNode == nil {
		return ""
	}
	// Get first base class
	typeNode := FindChildByType(baseClauseNode, "type_identifier")
	if typeNode == nil {
		return ""
	}
	return GetNodeText(typeNode, source)
}

func extractCPPMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "field_declaration_list")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"function_definition", "declaration"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		declaratorNode := FindChildByType(methodNode, "function_declarator")
		if declaratorNode == nil {
			continue
		}

		nameNode := FindChildByType(declaratorNode, "identifier")
		if nameNode == nil {
			nameNode = FindChildByType(declaratorNode, "field_identifier")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractCParameters(declaratorNode, source)
		returnType := extractCReturnType(methodNode, source)
		visibility := extractCPPVisibility(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsStatic:   hasCPPModifier(methodNode, source, "static"),
		})
	}

	return methods
}

func extractCPPFields(node *sitter.Node, source []byte) []types.PropertyInfo {
	bodyNode := FindChildByType(node, "field_declaration_list")
	if bodyNode == nil {
		return []types.PropertyInfo{}
	}

	fieldNodes := FindNodes(bodyNode, []string{"field_declaration"})
	var properties []types.PropertyInfo

	for _, fieldNode := range fieldNodes {
		// Skip function declarations
		if FindChildByType(fieldNode, "function_declarator") != nil {
			continue
		}

		visibility := extractCPPVisibility(fieldNode, source)

		// Get type
		typeName := ""
		for i := 0; i < int(fieldNode.ChildCount()); i++ {
			child := fieldNode.Child(i)
			if child != nil {
				childType := child.Type()
				if childType == "primitive_type" || childType == "type_identifier" {
					typeName = GetNodeText(child, source)
					break
				}
			}
		}

		// Get names
		declarators := FindNodes(fieldNode, []string{"field_identifier"})
		for _, decl := range declarators {
			properties = append(properties, types.PropertyInfo{
				Name:       GetNodeText(decl, source),
				Type:       typeName,
				Visibility: visibility,
			})
		}
	}

	return properties
}

func extractCPPVisibility(node *sitter.Node, source []byte) string {
	// Check for access specifier before this node
	parent := node.Parent()
	if parent == nil {
		return ""
	}

	// Walk backwards from this node to find access specifier
	nodeIndex := -1
	for i := 0; i < int(parent.ChildCount()); i++ {
		if parent.Child(i) == node {
			nodeIndex = i
			break
		}
	}

	for i := nodeIndex - 1; i >= 0; i-- {
		sibling := parent.Child(i)
		if sibling != nil && sibling.Type() == "access_specifier" {
			text := GetNodeText(sibling, source)
			if len(text) > 0 && text[len(text)-1] == ':' {
				text = text[:len(text)-1]
			}
			return text
		}
	}

	return ""
}

func hasCPPModifier(node *sitter.Node, source []byte, modifier string) bool {
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil && child.Type() == "storage_class_specifier" {
			if GetNodeText(child, source) == modifier {
				return true
			}
		}
	}
	return false
}

func countCArguments(argsNode *sitter.Node) int {
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

func detectCSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
