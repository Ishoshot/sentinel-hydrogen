package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/ocaml"
)

// AnalyzeOCaml analyzes OCaml source code and extracts semantic information
func AnalyzeOCaml(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(ocaml.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "ocaml",
			Errors:   []types.SyntaxError{{Message: "failed to parse OCaml"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "ocaml",
		Functions: extractOCamlFunctions(root, source),
		Classes:   extractOCamlModules(root, source),
		Imports:   extractOCamlImports(root, source),
		Symbols:   extractOCamlSymbols(root, source),
		Errors:    detectOCamlSyntaxErrors(root, source),
	}

	return result, nil
}

func extractOCamlFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	var functions []types.FunctionInfo

	// let bindings (functions)
	letBindings := FindNodes(root, []string{"let_binding", "value_definition"})
	for _, node := range letBindings {
		// Check if it's a function (has parameters)
		patternNode := FindChildByType(node, "value_name")
		if patternNode == nil {
			continue
		}

		name := GetNodeText(patternNode, source)

		// Check for parameters
		params := extractOCamlParameters(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
		})
	}

	return functions
}

func extractOCamlModules(root *sitter.Node, source []byte) []types.ClassInfo {
	var modules []types.ClassInfo

	moduleNodes := FindNodes(root, []string{"module_definition", "module_binding"})
	for _, node := range moduleNodes {
		nameNode := FindChildByType(node, "module_name")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)

		modules = append(modules, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
		})
	}

	return modules
}

func extractOCamlImports(root *sitter.Node, source []byte) []types.ImportInfo {
	var imports []types.ImportInfo

	// open statements
	openNodes := FindNodes(root, []string{"open_statement"})
	for _, node := range openNodes {
		moduleNode := FindChildByType(node, "module_path")
		if moduleNode == nil {
			continue
		}

		imports = append(imports, types.ImportInfo{
			Module: GetNodeText(moduleNode, source),
			Line:   int(node.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractOCamlSymbols(root *sitter.Node, source []byte) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Type definitions
	typeNodes := FindNodes(root, []string{"type_definition"})
	for _, node := range typeNodes {
		nameNode := FindChildByType(node, "type_constructor")
		if nameNode == nil {
			continue
		}

		symbols = append(symbols, types.SymbolInfo{
			Name: GetNodeText(nameNode, source),
			Kind: "type",
			Line: int(node.StartPoint().Row) + 1,
		})
	}

	return symbols
}

func extractOCamlParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	var params []types.ParameterInfo

	// Look for parameter patterns
	paramNodes := FindNodes(node, []string{"parameter", "labeled_argument"})
	for _, paramNode := range paramNodes {
		nameNode := FindChildByType(paramNode, "value_name")
		if nameNode == nil {
			nameNode = FindChildByType(paramNode, "value_pattern")
		}
		if nameNode == nil {
			continue
		}

		params = append(params, types.ParameterInfo{
			Name: GetNodeText(nameNode, source),
		})
	}

	return params
}

func detectOCamlSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
