package languages

import (
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/python"
	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzePython analyzes Python source code
func AnalyzePython(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(python.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "python",
			Errors:   []types.SyntaxError{{Message: "failed to parse Python"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "python",
		Functions: extractPyFunctions(root, source),
		Classes:   extractPyClasses(root, source),
		Imports:   extractPyImports(root, source),
		Calls:     extractPyCalls(root, source),
		Errors:    detectPySyntaxErrors(root),
	}

	return result, nil
}

func extractPyFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_definition"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		paramsNode := FindChildByType(node, "parameters")
		params := extractPyParameters(paramsNode, source)

		isAsync := false
		for i := 0; i < int(node.ChildCount()); i++ {
			child := node.Child(i)
			if child != nil && GetNodeText(child, source) == "async" {
				isAsync = true
				break
			}
		}

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			IsAsync:    isAsync,
		})
	}

	return functions
}

func extractPyClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class_definition"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractPyExtends(node, source)
		methods := extractPyMethods(node, source)

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

func extractPyImports(root *sitter.Node, source []byte) []types.ImportInfo {
	importNodes := FindNodes(root, []string{"import_statement", "import_from_statement"})
	var imports []types.ImportInfo

	for _, node := range importNodes {
		if node.Type() == "import_statement" {
			nameNode := FindChildByType(node, "dotted_name")
			if nameNode != nil {
				module := GetNodeText(nameNode, source)
				imports = append(imports, types.ImportInfo{
					Module:  module,
					Symbols: []string{},
					Line:    int(node.StartPoint().Row) + 1,
				})
			}
		} else if node.Type() == "import_from_statement" {
			moduleNode := FindChildByType(node, "dotted_name")
			module := ""
			if moduleNode != nil {
				module = GetNodeText(moduleNode, source)
			}

			symbols := []string{}
			for i := 0; i < int(node.ChildCount()); i++ {
				child := node.Child(i)
				if child != nil && child.Type() == "identifier" {
					symbols = append(symbols, GetNodeText(child, source))
				}
			}

			imports = append(imports, types.ImportInfo{
				Module:  module,
				Symbols: symbols,
				Line:    int(node.StartPoint().Row) + 1,
			})
		}
	}

	return imports
}

func extractPyCalls(root *sitter.Node, source []byte) []types.CallInfo {
	nodes := FindNodes(root, []string{"call"})
	var calls []types.CallInfo

	for _, node := range nodes {
		functionNode := node.Child(0)
		if functionNode == nil {
			continue
		}

		isMethodCall := functionNode.Type() == "attribute"
		callee := ""
		receiver := ""

		if isMethodCall {
			attrNode := FindChildByType(functionNode, "identifier")
			if attrNode != nil {
				callee = GetNodeText(attrNode, source)
			}
			objectNode := functionNode.Child(0)
			if objectNode != nil {
				receiver = GetNodeText(objectNode, source)
			}
		} else {
			callee = GetNodeText(functionNode, source)
		}

		argsNode := FindChildByType(node, "argument_list")
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

func extractPyParameters(paramsNode *sitter.Node, source []byte) []types.ParameterInfo {
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil || child.Type() != "identifier" {
			continue
		}

		name := GetNodeText(child, source)
		if name != "self" && name != "cls" {
			params = append(params, types.ParameterInfo{
				Name: name,
			})
		}
	}

	return params
}

func extractPyExtends(node *sitter.Node, source []byte) string {
	argListNode := FindChildByType(node, "argument_list")
	if argListNode == nil {
		return ""
	}

	firstArg := argListNode.Child(1) // Skip opening parenthesis
	if firstArg != nil {
		return GetNodeText(firstArg, source)
	}

	return ""
}

func extractPyMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "block")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindChildrenByType(bodyNode, "function_definition")
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		paramsNode := FindChildByType(methodNode, "parameters")
		params := extractPyParameters(paramsNode, source)

		isAsync := false
		for i := 0; i < int(methodNode.ChildCount()); i++ {
			child := methodNode.Child(i)
			if child != nil && GetNodeText(child, source) == "async" {
				isAsync = true
				break
			}
		}

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			IsAsync:    isAsync,
		})
	}

	return methods
}

func detectPySyntaxErrors(root *sitter.Node) []types.SyntaxError {
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
