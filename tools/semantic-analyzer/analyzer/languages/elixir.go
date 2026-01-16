package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/elixir"
)

// AnalyzeElixir analyzes Elixir source code and extracts semantic information
func AnalyzeElixir(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(elixir.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "elixir",
			Errors:   []types.SyntaxError{{Message: "failed to parse Elixir"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "elixir",
		Functions: extractElixirFunctions(root, source),
		Classes:   extractElixirModules(root, source),
		Imports:   extractElixirImports(root, source),
		Errors:    detectElixirSyntaxErrors(root, source),
	}

	return result, nil
}

func extractElixirFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// Find def and defp (private) function definitions
	calls := FindNodes(root, []string{"call"})
	for _, node := range calls {
		targetNode := FindChildByType(node, "identifier")
		if targetNode == nil {
			continue
		}

		target := GetNodeText(targetNode, source)
		if target != "def" && target != "defp" {
			continue
		}

		// Get the function name from arguments
		argsNode := FindChildByType(node, "arguments")
		if argsNode == nil {
			continue
		}

		// First argument is the function head
		if argsNode.ChildCount() < 1 {
			continue
		}

		headNode := argsNode.Child(0)
		if headNode == nil {
			continue
		}

		// Function name could be in a call (with params) or just an identifier
		var funcName string
		var params []types.ParameterInfo

		if headNode.Type() == "call" {
			funcNameNode := FindChildByType(headNode, "identifier")
			if funcNameNode != nil {
				funcName = GetNodeText(funcNameNode, source)
			}
			params = extractElixirParameters(headNode, source)
		} else if headNode.Type() == "identifier" {
			funcName = GetNodeText(headNode, source)
		}

		if funcName != "" {
			visibility := "public"
			if target == "defp" {
				visibility = "private"
			}

			functions = append(functions, types.FunctionInfo{
				Name:       funcName,
				LineStart:  int(node.StartPoint().Row) + 1,
				LineEnd:    int(node.EndPoint().Row) + 1,
				Parameters: params,
				Visibility: visibility,
			})
		}
	}

	return functions
}

func extractElixirModules(root *sitter.Node, source []byte) []types.ClassInfo {
	var modules []types.ClassInfo

	calls := FindNodes(root, []string{"call"})
	for _, node := range calls {
		targetNode := FindChildByType(node, "identifier")
		if targetNode == nil {
			continue
		}

		target := GetNodeText(targetNode, source)
		if target != "defmodule" {
			continue
		}

		// Get module name from arguments
		argsNode := FindChildByType(node, "arguments")
		if argsNode == nil || argsNode.ChildCount() < 1 {
			continue
		}

		nameNode := argsNode.Child(0)
		if nameNode == nil {
			continue
		}

		moduleName := GetNodeText(nameNode, source)

		// Extract methods from the do block
		methods := extractElixirModuleMethods(node, source)

		modules = append(modules, types.ClassInfo{
			Name:      moduleName,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	return modules
}

func extractElixirImports(root *sitter.Node, source []byte) []types.ImportInfo {
	var imports []types.ImportInfo

	calls := FindNodes(root, []string{"call"})
	for _, node := range calls {
		targetNode := FindChildByType(node, "identifier")
		if targetNode == nil {
			continue
		}

		target := GetNodeText(targetNode, source)
		if target != "import" && target != "alias" && target != "use" && target != "require" {
			continue
		}

		argsNode := FindChildByType(node, "arguments")
		if argsNode == nil || argsNode.ChildCount() < 1 {
			continue
		}

		moduleNode := argsNode.Child(0)
		if moduleNode == nil {
			continue
		}

		moduleName := GetNodeText(moduleNode, source)

		imports = append(imports, types.ImportInfo{
			Module: moduleName,
			Line:   int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractElixirParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	var params []types.ParameterInfo

	argsNode := FindChildByType(node, "arguments")
	if argsNode == nil {
		return params
	}

	for i := 0; i < int(argsNode.ChildCount()); i++ {
		child := argsNode.Child(i)
		if child == nil || child.Type() == "," {
			continue
		}

		paramName := GetNodeText(child, source)
		params = append(params, types.ParameterInfo{
			Name: paramName,
		})
	}

	return params
}

func extractElixirModuleMethods(moduleNode *sitter.Node, source []byte) []types.FunctionInfo {
	var methods []types.FunctionInfo

	// Find do block
	doBlockNode := FindChildByType(moduleNode, "do_block")
	if doBlockNode == nil {
		return methods
	}

	// Find def/defp calls within the do block
	calls := FindNodes(doBlockNode, []string{"call"})
	for _, node := range calls {
		targetNode := FindChildByType(node, "identifier")
		if targetNode == nil {
			continue
		}

		target := GetNodeText(targetNode, source)
		if target != "def" && target != "defp" {
			continue
		}

		argsNode := FindChildByType(node, "arguments")
		if argsNode == nil || argsNode.ChildCount() < 1 {
			continue
		}

		headNode := argsNode.Child(0)
		if headNode == nil {
			continue
		}

		var funcName string
		var params []types.ParameterInfo

		if headNode.Type() == "call" {
			funcNameNode := FindChildByType(headNode, "identifier")
			if funcNameNode != nil {
				funcName = GetNodeText(funcNameNode, source)
			}
			params = extractElixirParameters(headNode, source)
		} else if headNode.Type() == "identifier" {
			funcName = GetNodeText(headNode, source)
		}

		if funcName != "" {
			visibility := "public"
			if target == "defp" {
				visibility = "private"
			}

			methods = append(methods, types.FunctionInfo{
				Name:       funcName,
				LineStart:  int(node.StartPoint().Row) + 1,
				LineEnd:    int(node.EndPoint().Row) + 1,
				Parameters: params,
				Visibility: visibility,
			})
		}
	}

	return methods
}

func detectElixirSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
