package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/ruby"
)

// AnalyzeRuby analyzes Ruby source code and extracts semantic information
func AnalyzeRuby(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(ruby.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "ruby",
			Errors:   []types.SyntaxError{{Message: "failed to parse Ruby"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "ruby",
		Functions: extractRubyFunctions(root, source),
		Classes:   extractRubyClasses(root, source),
		Imports:   extractRubyImports(root, source),
		Calls:     extractRubyCalls(root, source),
		Errors:    detectRubySyntaxErrors(root, source),
	}

	return result, nil
}

func extractRubyFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"method", "singleton_method"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		// Skip methods inside classes
		parent := node.Parent()
		if parent != nil && (parent.Type() == "class" || parent.Type() == "body_statement") {
			grandparent := parent.Parent()
			if grandparent != nil && grandparent.Type() == "class" {
				continue
			}
		}

		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractRubyParameters(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			Visibility: "public",
		})
	}

	return functions
}

func extractRubyClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	nodes := FindNodes(root, []string{"class"})
	var classes []types.ClassInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "constant")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractRubyExtends(node, source)
		methods := extractRubyMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Extends:   extends,
			Methods:   methods,
		})
	}

	// Also extract modules
	moduleNodes := FindNodes(root, []string{"module"})
	for _, node := range moduleNodes {
		nameNode := FindChildByType(node, "constant")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractRubyMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	return classes
}

func extractRubyImports(root *sitter.Node, source []byte) []types.ImportInfo {
	var imports []types.ImportInfo

	// require statements
	requireNodes := FindNodes(root, []string{"call"})
	for _, node := range requireNodes {
		methodNode := FindChildByType(node, "identifier")
		if methodNode == nil {
			continue
		}

		methodName := GetNodeText(methodNode, source)
		if methodName != "require" && methodName != "require_relative" && methodName != "load" {
			continue
		}

		argsNode := FindChildByType(node, "argument_list")
		if argsNode == nil {
			continue
		}

		stringNode := FindChildByType(argsNode, "string")
		if stringNode == nil {
			continue
		}

		module := GetNodeText(stringNode, source)
		// Remove quotes
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

func extractRubyCalls(root *sitter.Node, source []byte) []types.CallInfo {
	callNodes := FindNodes(root, []string{"call", "method_call"})
	var calls []types.CallInfo

	for _, node := range callNodes {
		methodNode := FindChildByType(node, "identifier")
		if methodNode == nil {
			continue
		}

		callee := GetNodeText(methodNode, source)

		// Skip require/require_relative (already handled as imports)
		if callee == "require" || callee == "require_relative" || callee == "load" {
			continue
		}

		argsNode := FindChildByType(node, "argument_list")
		argCount := countRubyArguments(argsNode)

		// Check for receiver
		receiver := ""
		isMethodCall := false
		receiverNode := node.Child(0)
		if receiverNode != nil && receiverNode.Type() != "identifier" {
			receiver = GetNodeText(receiverNode, source)
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

func extractRubyParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "method_parameters")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil {
			continue
		}

		if child.Type() == "identifier" {
			params = append(params, types.ParameterInfo{
				Name: GetNodeText(child, source),
			})
		} else if child.Type() == "optional_parameter" || child.Type() == "keyword_parameter" {
			nameNode := FindChildByType(child, "identifier")
			if nameNode != nil {
				params = append(params, types.ParameterInfo{
					Name: GetNodeText(nameNode, source),
				})
			}
		}
	}

	return params
}

func extractRubyExtends(node *sitter.Node, source []byte) string {
	superclassNode := FindChildByType(node, "superclass")
	if superclassNode == nil {
		return ""
	}
	constNode := FindChildByType(superclassNode, "constant")
	if constNode == nil {
		return ""
	}
	return GetNodeText(constNode, source)
}

func extractRubyMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "body_statement")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"method", "singleton_method"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractRubyParameters(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			Visibility: "public",
		})
	}

	return methods
}

func countRubyArguments(argsNode *sitter.Node) int {
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

func detectRubySyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
