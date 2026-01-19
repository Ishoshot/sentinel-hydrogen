package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/bash"
)

// AnalyzeBash analyzes Bash/Shell source code and extracts semantic information
func AnalyzeBash(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(bash.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "bash",
			Errors:   []types.SyntaxError{{Message: "failed to parse Bash"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "bash",
		Functions: extractBashFunctions(root, source),
		Symbols:   extractBashSymbols(root, source),
		Calls:     extractBashCalls(root, source),
		Errors:    detectBashSyntaxErrors(root, source),
	}

	return result, nil
}

func extractBashFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_definition"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "word")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)

		functions = append(functions, types.FunctionInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
		})
	}

	return functions
}

func extractBashSymbols(root *sitter.Node, source []byte) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Extract variable assignments
	assignments := FindNodes(root, []string{"variable_assignment"})
	for _, assignment := range assignments {
		nameNode := FindChildByType(assignment, "variable_name")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		symbols = append(symbols, types.SymbolInfo{
			Name: name,
			Kind: "variable",
			Line: int(assignment.StartPoint().Row) + 1,
		})
	}

	// Extract exported variables
	declarations := FindNodes(root, []string{"declaration_command"})
	for _, decl := range declarations {
		// Check if it's an export
		firstChild := decl.Child(0)
		if firstChild == nil || GetNodeText(firstChild, source) != "export" {
			continue
		}

		// Find variable assignments in the export
		assignmentNodes := FindNodes(decl, []string{"variable_assignment"})
		for _, assignment := range assignmentNodes {
			nameNode := FindChildByType(assignment, "variable_name")
			if nameNode != nil {
				symbols = append(symbols, types.SymbolInfo{
					Name: GetNodeText(nameNode, source),
					Kind: "export",
					Line: int(decl.StartPoint().Row) + 1,
				})
			}
		}
	}

	return symbols
}

func extractBashCalls(root *sitter.Node, source []byte) []types.CallInfo {
	var calls []types.CallInfo

	// Extract command calls
	commands := FindNodes(root, []string{"command"})
	for _, cmd := range commands {
		nameNode := FindChildByType(cmd, "command_name")
		if nameNode == nil {
			continue
		}

		wordNode := FindChildByType(nameNode, "word")
		if wordNode == nil {
			continue
		}

		callee := GetNodeText(wordNode, source)

		// Count arguments
		argCount := 0
		for i := 0; i < int(cmd.ChildCount()); i++ {
			child := cmd.Child(i)
			if child != nil && child.Type() == "word" && child != wordNode {
				argCount++
			}
		}

		calls = append(calls, types.CallInfo{
			Callee:         callee,
			Line:           int(cmd.StartPoint().Row) + 1,
			ArgumentsCount: argCount,
		})
	}

	return calls
}

func detectBashSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
