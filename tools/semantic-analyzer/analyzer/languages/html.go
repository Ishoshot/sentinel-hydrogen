package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/html"
)

// AnalyzeHTML analyzes HTML source code and extracts semantic information
func AnalyzeHTML(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(html.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "html",
			Errors:   []types.SyntaxError{{Message: "failed to parse HTML"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language: "html",
		Symbols:  extractHTMLSymbols(root, source),
		Imports:  extractHTMLImports(root, source),
		Errors:   detectHTMLSyntaxErrors(root, source),
	}

	return result, nil
}

func extractHTMLSymbols(root *sitter.Node, source []byte) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Extract elements with IDs
	elements := FindNodes(root, []string{"element", "self_closing_tag"})
	for _, element := range elements {
		startTag := FindChildByType(element, "start_tag")
		if startTag == nil {
			startTag = element
		}

		// Find attributes
		attrs := FindNodes(startTag, []string{"attribute"})
		for _, attr := range attrs {
			nameNode := FindChildByType(attr, "attribute_name")
			if nameNode == nil {
				continue
			}

			attrName := GetNodeText(nameNode, source)
			if attrName == "id" {
				valueNode := FindChildByType(attr, "attribute_value")
				if valueNode == nil {
					valueNode = FindChildByType(attr, "quoted_attribute_value")
				}
				if valueNode != nil {
					value := GetNodeText(valueNode, source)
					// Remove quotes if present
					if len(value) >= 2 && (value[0] == '"' || value[0] == '\'') {
						value = value[1 : len(value)-1]
					}
					symbols = append(symbols, types.SymbolInfo{
						Name: value,
						Kind: "id",
						Line: int(attr.StartPoint().Row) + 1,
					})
				}
			} else if attrName == "class" {
				valueNode := FindChildByType(attr, "attribute_value")
				if valueNode == nil {
					valueNode = FindChildByType(attr, "quoted_attribute_value")
				}
				if valueNode != nil {
					value := GetNodeText(valueNode, source)
					// Remove quotes if present
					if len(value) >= 2 && (value[0] == '"' || value[0] == '\'') {
						value = value[1 : len(value)-1]
					}
					symbols = append(symbols, types.SymbolInfo{
						Name: value,
						Kind: "class",
						Line: int(attr.StartPoint().Row) + 1,
					})
				}
			}
		}
	}

	return symbols
}

func extractHTMLImports(root *sitter.Node, source []byte) []types.ImportInfo {
	var imports []types.ImportInfo

	elements := FindNodes(root, []string{"element", "self_closing_tag"})
	for _, element := range elements {
		startTag := FindChildByType(element, "start_tag")
		if startTag == nil {
			startTag = element
		}

		tagNameNode := FindChildByType(startTag, "tag_name")
		if tagNameNode == nil {
			continue
		}

		tagName := GetNodeText(tagNameNode, source)

		// Script tags
		if tagName == "script" {
			attrs := FindNodes(startTag, []string{"attribute"})
			for _, attr := range attrs {
				nameNode := FindChildByType(attr, "attribute_name")
				if nameNode != nil && GetNodeText(nameNode, source) == "src" {
					valueNode := FindChildByType(attr, "attribute_value")
					if valueNode == nil {
						valueNode = FindChildByType(attr, "quoted_attribute_value")
					}
					if valueNode != nil {
						value := GetNodeText(valueNode, source)
						if len(value) >= 2 && (value[0] == '"' || value[0] == '\'') {
							value = value[1 : len(value)-1]
						}
						imports = append(imports, types.ImportInfo{
							Module: value,
							Line:   int(attr.StartPoint().Row) + 1,
						})
					}
				}
			}
		}

		// Link tags (stylesheets)
		if tagName == "link" {
			attrs := FindNodes(startTag, []string{"attribute"})
			isStylesheet := false
			href := ""
			line := 0

			for _, attr := range attrs {
				nameNode := FindChildByType(attr, "attribute_name")
				if nameNode == nil {
					continue
				}

				attrName := GetNodeText(nameNode, source)
				valueNode := FindChildByType(attr, "attribute_value")
				if valueNode == nil {
					valueNode = FindChildByType(attr, "quoted_attribute_value")
				}
				if valueNode == nil {
					continue
				}

				value := GetNodeText(valueNode, source)
				if len(value) >= 2 && (value[0] == '"' || value[0] == '\'') {
					value = value[1 : len(value)-1]
				}

				if attrName == "rel" && value == "stylesheet" {
					isStylesheet = true
				}
				if attrName == "href" {
					href = value
					line = int(attr.StartPoint().Row) + 1
				}
			}

			if isStylesheet && href != "" {
				imports = append(imports, types.ImportInfo{
					Module: href,
					Line:   line,
				})
			}
		}
	}

	return imports
}

func detectHTMLSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
