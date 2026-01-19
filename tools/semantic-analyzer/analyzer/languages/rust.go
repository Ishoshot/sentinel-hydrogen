package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/rust"
)

// AnalyzeRust analyzes Rust source code
func AnalyzeRust(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(rust.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "rust",
			Errors:   []types.SyntaxError{{Message: "failed to parse Rust"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "rust",
		Functions: extractRustFunctions(root, source),
		Imports:   extractRustImports(root, source),
		Calls:     extractRustCalls(root, source),
		Errors:    detectRustSyntaxErrors(root),
	}

	return result, nil
}

func extractRustFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_item"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		paramsNode := FindChildByType(node, "parameters")
		params := extractRustParameters(paramsNode, source)

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

func extractRustImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"use_declaration"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		scopeNode := node.Child(1)
		if scopeNode == nil {
			continue
		}

		module := GetNodeText(scopeNode, source)
		imports = append(imports, types.ImportInfo{
			Module:  module,
			Symbols: []string{},
			Line:    int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractRustCalls(root *sitter.Node, source []byte) []types.CallInfo {
	nodes := FindNodes(root, []string{"call_expression"})
	var calls []types.CallInfo

	for _, node := range nodes {
		functionNode := node.Child(0)
		if functionNode == nil {
			continue
		}

		isMethodCall := functionNode.Type() == "field_expression"
		callee := ""
		receiver := ""

		if isMethodCall {
			fieldNode := FindChildByType(functionNode, "field_identifier")
			if fieldNode != nil {
				callee = GetNodeText(fieldNode, source)
			}
			valueNode := functionNode.Child(0)
			if valueNode != nil {
				receiver = GetNodeText(valueNode, source)
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

func extractRustParameters(paramsNode *sitter.Node, source []byte) []types.ParameterInfo {
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil || child.Type() != "parameter" {
			continue
		}

		patternNode := FindChildByType(child, "identifier")
		if patternNode != nil {
			name := GetNodeText(patternNode, source)
			params = append(params, types.ParameterInfo{
				Name: name,
			})
		}
	}

	return params
}

func detectRustSyntaxErrors(root *sitter.Node) []types.SyntaxError {
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
