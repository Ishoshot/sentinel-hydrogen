package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/lua"
)

// AnalyzeLua analyzes Lua source code and extracts semantic information
func AnalyzeLua(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(lua.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "lua",
			Errors:   []types.SyntaxError{{Message: "failed to parse Lua"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "lua",
		Functions: extractLuaFunctions(root, source),
		Symbols:   extractLuaSymbols(root, source),
		Imports:   extractLuaImports(root, source),
		Calls:     extractLuaCalls(root, source),
		Errors:    detectLuaSyntaxErrors(root, source),
	}

	return result, nil
}

func extractLuaFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Function declarations
	funcNodes := FindNodes(root, []string{"function_declaration"})
	for _, node := range funcNodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			// Try function_name for method-style declarations
			nameNode = FindChildByType(node, "function_name")
		}
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractLuaParameters(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
		})
	}

	// Local function declarations
	localFuncNodes := FindNodes(root, []string{"local_function_declaration"})
	for _, node := range localFuncNodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractLuaParameters(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			Visibility: "local",
		})
	}

	return functions
}

func extractLuaSymbols(root *sitter.Node, source []byte) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Variable assignments
	assignments := FindNodes(root, []string{"variable_declaration"})
	for _, node := range assignments {
		nameNodes := FindNodes(node, []string{"identifier"})
		for _, nameNode := range nameNodes {
			name := GetNodeText(nameNode, source)
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "variable",
				Line: int(node.StartPoint().Row) + 1,
			})
		}
	}

	// Local variable declarations
	localVars := FindNodes(root, []string{"local_variable_declaration"})
	for _, node := range localVars {
		nameNodes := FindNodes(node, []string{"identifier"})
		for _, nameNode := range nameNodes {
			name := GetNodeText(nameNode, source)
			symbols = append(symbols, types.SymbolInfo{
				Name: name,
				Kind: "local",
				Line: int(node.StartPoint().Row) + 1,
			})
		}
	}

	return symbols
}

func extractLuaImports(root *sitter.Node, source []byte) []types.ImportInfo {
	var imports []types.ImportInfo

	// require calls
	calls := FindNodes(root, []string{"function_call"})
	for _, node := range calls {
		funcNode := FindChildByType(node, "identifier")
		if funcNode == nil {
			continue
		}

		funcName := GetNodeText(funcNode, source)
		if funcName != "require" {
			continue
		}

		argsNode := FindChildByType(node, "arguments")
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

func extractLuaCalls(root *sitter.Node, source []byte) []types.CallInfo {
	var calls []types.CallInfo

	callNodes := FindNodes(root, []string{"function_call"})
	for _, node := range callNodes {
		funcNode := node.Child(0)
		if funcNode == nil {
			continue
		}

		callee := ""
		receiver := ""
		isMethodCall := false

		if funcNode.Type() == "identifier" {
			callee = GetNodeText(funcNode, source)
		} else if funcNode.Type() == "method_index_expression" || funcNode.Type() == "dot_index_expression" {
			// obj:method() or obj.method()
			for i := 0; i < int(funcNode.ChildCount()); i++ {
				child := funcNode.Child(i)
				if child != nil && child.Type() == "identifier" {
					if callee == "" {
						receiver = GetNodeText(child, source)
					} else {
						callee = GetNodeText(child, source)
					}
				}
			}
			if receiver != "" {
				isMethodCall = true
			}
		}

		// Skip require calls (already handled as imports)
		if callee == "require" {
			continue
		}

		argsNode := FindChildByType(node, "arguments")
		argCount := countLuaArguments(argsNode)

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

func extractLuaParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "parameters")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	for i := 0; i < int(paramsNode.ChildCount()); i++ {
		child := paramsNode.Child(i)
		if child == nil || child.Type() == "," || child.Type() == "(" || child.Type() == ")" {
			continue
		}

		if child.Type() == "identifier" || child.Type() == "spread" {
			params = append(params, types.ParameterInfo{
				Name: GetNodeText(child, source),
			})
		}
	}

	return params
}

func countLuaArguments(argsNode *sitter.Node) int {
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

func detectLuaSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
