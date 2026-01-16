package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/yaml"
)

// AnalyzeYAML analyzes YAML source code and extracts semantic information
func AnalyzeYAML(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(yaml.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "yaml",
			Errors:   []types.SyntaxError{{Message: "failed to parse YAML"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language: "yaml",
		Symbols:  extractYAMLSymbols(root, source),
		Errors:   detectYAMLSyntaxErrors(root, source),
	}

	return result, nil
}

func extractYAMLSymbols(root *sitter.Node, source []byte) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Extract top-level keys
	blockMappings := FindNodes(root, []string{"block_mapping"})
	for _, mapping := range blockMappings {
		pairs := FindChildrenByType(mapping, "block_mapping_pair")
		for _, pair := range pairs {
			keyNode := FindChildByType(pair, "flow_node")
			if keyNode == nil {
				continue
			}

			// Get the actual key text
			plainNode := FindChildByType(keyNode, "plain_scalar")
			if plainNode == nil {
				continue
			}

			keyText := GetNodeText(plainNode, source)
			symbols = append(symbols, types.SymbolInfo{
				Name: keyText,
				Kind: "key",
				Line: int(pair.StartPoint().Row) + 1,
			})
		}
	}

	// Also check for flow mappings (inline)
	flowMappings := FindNodes(root, []string{"flow_mapping"})
	for _, mapping := range flowMappings {
		pairs := FindNodes(mapping, []string{"flow_pair"})
		for _, pair := range pairs {
			keyNode := pair.Child(0)
			if keyNode == nil {
				continue
			}

			keyText := GetNodeText(keyNode, source)
			symbols = append(symbols, types.SymbolInfo{
				Name: keyText,
				Kind: "key",
				Line: int(pair.StartPoint().Row) + 1,
			})
		}
	}

	return symbols
}

func detectYAMLSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
