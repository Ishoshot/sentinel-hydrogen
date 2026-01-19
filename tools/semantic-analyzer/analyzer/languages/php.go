package languages

import (
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/php"
)

// AnalyzePHP analyzes PHP source code and extracts semantic information
func AnalyzePHP(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(php.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "php",
			Errors:   []types.SyntaxError{{Message: "failed to parse PHP"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "php",
		Functions: extractPHPFunctions(root, source),
		Classes:   extractPHPClasses(root, source),
		Imports:   extractPHPImports(root, source),
		Calls:     extractPHPCalls(root, source),
		Errors:    detectPHPSyntaxErrors(root, source),
	}

	return result, nil
}

func extractPHPFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_definition"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "name")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractPHPParameters(node, source)
		returnType := extractPHPReturnType(node, source)
		visibility := extractPHPVisibility(node)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsAsync:    false,
			IsStatic:   hasModifier(node, "static"),
		})
	}

	return functions
}

func extractPHPClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class_declaration"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "name")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractPHPExtends(node, source)
		implements := extractPHPImplements(node, source)
		methods := extractPHPMethods(node, source)
		properties := extractPHPProperties(node, source)

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

	return classes
}

func extractPHPImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"namespace_use_declaration"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		clauseNodes := FindChildrenByType(node, "namespace_use_clause")
		for _, clauseNode := range clauseNodes {
			nameNode := FindChildByType(clauseNode, "namespace_name")
			if nameNode == nil {
				nameNode = clauseNode
			}

			module := GetNodeText(nameNode, source)
			module = strings.TrimPrefix(module, "\\")

			imports = append(imports, types.ImportInfo{
				Module:  module,
				Symbols: []string{},
				Line:    int(node.StartPoint().Row) + 1,
			})
		}
	}

	return imports
}

func extractPHPCalls(root *sitter.Node, source []byte) []types.CallInfo {
	functionCalls := FindNodes(root, []string{"function_call_expression"})
	memberCalls := FindNodes(root, []string{"member_call_expression"})
	var calls []types.CallInfo

	for _, node := range functionCalls {
		functionNode := node.Child(0)
		if functionNode == nil {
			continue
		}

		callee := GetNodeText(functionNode, source)
		argsNode := FindChildByType(node, "arguments")
		argCount := CountArguments(argsNode)

		calls = append(calls, types.CallInfo{
			Callee:         callee,
			Line:           int(node.StartPoint().Row) + 1,
			ArgumentsCount: argCount,
			IsMethodCall:   false,
		})
	}

	for _, node := range memberCalls {
		nameNode := FindChildByType(node, "name")
		if nameNode == nil {
			continue
		}

		callee := GetNodeText(nameNode, source)
		objectNode := node.Child(0)
		receiver := ""
		if objectNode != nil {
			receiver = GetNodeText(objectNode, source)
		}

		argsNode := FindChildByType(node, "arguments")
		argCount := CountArguments(argsNode)

		calls = append(calls, types.CallInfo{
			Callee:         callee,
			Line:           int(node.StartPoint().Row) + 1,
			ArgumentsCount: argCount,
			IsMethodCall:   true,
			Receiver:       receiver,
		})
	}

	return calls
}

func extractPHPParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "formal_parameters")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil || child.Type() != "simple_parameter" && child.Type() != "property_promotion_parameter" {
			continue
		}

		nameNode := FindChildByType(child, "variable_name")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		typeNode := FindChildByType(child, "type")
		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		params = append(params, types.ParameterInfo{
			Name: name,
			Type: typeName,
		})
	}

	return params
}

func extractPHPReturnType(node *sitter.Node, source []byte) string {
	returnTypeNode := FindChildByType(node, "return_type")
	if returnTypeNode == nil {
		return ""
	}
	return GetNodeText(returnTypeNode, source)
}

func extractPHPVisibility(node *sitter.Node) string {
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child == nil {
			continue
		}
		text := child.Type()
		if text == "public" || text == "private" || text == "protected" {
			return text
		}
	}
	return ""
}

func hasModifier(node *sitter.Node, modifier string) bool {
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil && child.Type() == modifier {
			return true
		}
	}
	return false
}

func extractPHPExtends(node *sitter.Node, source []byte) string {
	baseClauseNode := FindChildByType(node, "base_clause")
	if baseClauseNode == nil {
		return ""
	}
	nameNode := FindChildByType(baseClauseNode, "name")
	if nameNode == nil {
		return ""
	}
	return GetNodeText(nameNode, source)
}

func extractPHPImplements(node *sitter.Node, source []byte) []string {
	interfaceClauseNode := FindChildByType(node, "class_interface_clause")
	if interfaceClauseNode == nil {
		return []string{}
	}

	var implements []string
	for i := 0; i < int(interfaceClauseNode.ChildCount()); i++ {
		child := interfaceClauseNode.Child(i)
		if child != nil && child.Type() == "name" {
			implements = append(implements, GetNodeText(child, source))
		}
	}
	return implements
}

func extractPHPMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "declaration_list")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindChildrenByType(bodyNode, "method_declaration")
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "name")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractPHPParameters(methodNode, source)
		returnType := extractPHPReturnType(methodNode, source)
		visibility := extractPHPVisibility(methodNode)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
			Visibility: visibility,
			IsStatic:   hasModifier(methodNode, "static"),
		})
	}

	return methods
}

func extractPHPProperties(node *sitter.Node, source []byte) []types.PropertyInfo {
	bodyNode := FindChildByType(node, "declaration_list")
	if bodyNode == nil {
		return []types.PropertyInfo{}
	}

	propNodes := FindChildrenByType(bodyNode, "property_declaration")
	var properties []types.PropertyInfo

	for _, propNode := range propNodes {
		visibility := extractPHPVisibility(propNode)
		typeNode := FindChildByType(propNode, "type")
		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		// Extract property elements
		propElements := FindChildrenByType(propNode, "property_element")
		for _, element := range propElements {
			nameNode := FindChildByType(element, "variable_name")
			if nameNode == nil {
				continue
			}

			name := GetNodeText(nameNode, source)
			properties = append(properties, types.PropertyInfo{
				Name:       name,
				Type:       typeName,
				Visibility: visibility,
			})
		}
	}

	return properties
}

func detectPHPSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
