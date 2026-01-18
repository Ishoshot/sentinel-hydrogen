package languages

import (
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/golang"
	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeGo analyzes Go source code
func AnalyzeGo(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(golang.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "go",
			Errors:   []types.SyntaxError{{Message: "failed to parse Go"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "go",
		Functions: extractGoFunctions(root, source),
		Imports:   extractGoImports(root, source),
		Calls:     extractGoCalls(root, source),
		Errors:    detectGoSyntaxErrors(root),
	}

	return result, nil
}

func extractGoFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_declaration", "method_declaration"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		paramsNode := FindChildByType(node, "parameter_list")
		params := extractGoParameters(paramsNode, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
		})
	}

	return functions
}

func extractGoImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"import_spec"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		pathNode := FindChildByType(node, "interpreted_string_literal")
		if pathNode == nil {
			continue
		}

		module := GetNodeText(pathNode, source)
		// Remove quotes
		if len(module) >= 2 {
			module = module[1 : len(module)-1]
		}

		imports = append(imports, types.ImportInfo{
			Module:  module,
			Symbols: []string{},
			Line:    int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractGoCalls(root *sitter.Node, source []byte) []types.CallInfo {
	nodes := FindNodes(root, []string{"call_expression"})
	var calls []types.CallInfo

	for _, node := range nodes {
		functionNode := node.Child(0)
		if functionNode == nil {
			continue
		}

		isMethodCall := functionNode.Type() == "selector_expression"
		callee := ""
		receiver := ""

		if isMethodCall {
			fieldNode := FindChildByType(functionNode, "field_identifier")
			if fieldNode != nil {
				callee = GetNodeText(fieldNode, source)
			}
			operandNode := functionNode.Child(0)
			if operandNode != nil {
				receiver = GetNodeText(operandNode, source)
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

func extractGoParameters(paramsNode *sitter.Node, source []byte) []types.ParameterInfo {
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil || child.Type() != "parameter_declaration" {
			continue
		}

		nameNode := FindChildByType(child, "identifier")
		if nameNode != nil {
			name := GetNodeText(nameNode, source)
			params = append(params, types.ParameterInfo{
				Name: name,
			})
		}
	}

	return params
}

func detectGoSyntaxErrors(root *sitter.Node) []types.SyntaxError {
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
