package languages

import (
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/css"
)

// AnalyzeCSS analyzes CSS source code and extracts semantic information
func AnalyzeCSS(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(css.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "css",
			Errors:   []types.SyntaxError{{Message: "failed to parse CSS"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language: "css",
		Symbols:  extractCSSSymbols(root, source),
		Imports:  extractCSSImports(root, source),
		Errors:   detectCSSSyntaxErrors(root, source),
	}

	return result, nil
}

// AnalyzeSCSS analyzes SCSS source code - uses CSS parser as fallback
// Note: Full SCSS support would require a SCSS-specific parser
func AnalyzeSCSS(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(css.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "scss",
			Errors:   []types.SyntaxError{{Message: "failed to parse SCSS"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language: "scss",
		Symbols:  extractCSSSymbols(root, source),
		Imports:  extractSCSSImports(root, source),
		Errors:   detectCSSSyntaxErrors(root, source),
	}

	return result, nil
}

func extractCSSSymbols(root *sitter.Node, source []byte) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Extract CSS selectors
	rulesets := FindNodes(root, []string{"rule_set"})
	for _, ruleset := range rulesets {
		selectorsNode := FindChildByType(ruleset, "selectors")
		if selectorsNode == nil {
			continue
		}

		// Get all selectors
		for i := 0; i < int(selectorsNode.ChildCount()); i++ {
			child := selectorsNode.Child(i)
			if child == nil || child.Type() == "," {
				continue
			}

			selectorText := GetNodeText(child, source)
			kind := "selector"

			// Determine selector type
			if strings.HasPrefix(selectorText, "#") {
				kind = "id"
			} else if strings.HasPrefix(selectorText, ".") {
				kind = "class"
			} else if strings.HasPrefix(selectorText, "@") {
				kind = "at-rule"
			}

			symbols = append(symbols, types.SymbolInfo{
				Name: selectorText,
				Kind: kind,
				Line: int(child.StartPoint().Row) + 1,
			})
		}
	}

	// Extract CSS variables (custom properties)
	declarations := FindNodes(root, []string{"declaration"})
	for _, decl := range declarations {
		propertyNode := FindChildByType(decl, "property_name")
		if propertyNode == nil {
			continue
		}

		property := GetNodeText(propertyNode, source)
		if strings.HasPrefix(property, "--") {
			symbols = append(symbols, types.SymbolInfo{
				Name: property,
				Kind: "variable",
				Line: int(decl.StartPoint().Row) + 1,
			})
		}
	}

	// Extract keyframes
	keyframes := FindNodes(root, []string{"keyframes_statement"})
	for _, kf := range keyframes {
		nameNode := FindChildByType(kf, "keyframes_name")
		if nameNode == nil {
			continue
		}

		symbols = append(symbols, types.SymbolInfo{
			Name: GetNodeText(nameNode, source),
			Kind: "keyframes",
			Line: int(kf.StartPoint().Row) + 1,
		})
	}

	return symbols
}

func extractCSSImports(root *sitter.Node, source []byte) []types.ImportInfo {
	var imports []types.ImportInfo

	importRules := FindNodes(root, []string{"import_statement"})
	for _, importRule := range importRules {
		// Find the URL or string
		urlNode := FindChildByType(importRule, "call_expression")
		if urlNode == nil {
			urlNode = FindChildByType(importRule, "string_value")
		}
		if urlNode == nil {
			continue
		}

		value := GetNodeText(urlNode, source)
		// Extract URL from url() or quotes
		if strings.HasPrefix(value, "url(") {
			value = strings.TrimPrefix(value, "url(")
			value = strings.TrimSuffix(value, ")")
		}
		value = strings.Trim(value, "\"'")

		imports = append(imports, types.ImportInfo{
			Module: value,
			Line:   int(importRule.StartPoint().Row) + 1,
		})
	}

	return imports
}

func extractSCSSImports(root *sitter.Node, source []byte) []types.ImportInfo {
	imports := extractCSSImports(root, source)

	// Also look for @use and @forward (SCSS specific)
	// These might be parsed as at_rules
	atRules := FindNodes(root, []string{"at_rule"})
	for _, rule := range atRules {
		keyword := ""
		for i := 0; i < int(rule.ChildCount()); i++ {
			child := rule.Child(i)
			if child != nil && child.Type() == "at_keyword" {
				keyword = GetNodeText(child, source)
				break
			}
		}

		if keyword == "@use" || keyword == "@forward" || keyword == "@import" {
			// Find string value
			for i := 0; i < int(rule.ChildCount()); i++ {
				child := rule.Child(i)
				if child != nil && child.Type() == "string_value" {
					value := GetNodeText(child, source)
					value = strings.Trim(value, "\"'")
					imports = append(imports, types.ImportInfo{
						Module: value,
						Line:   int(rule.StartPoint().Row) + 1,
					})
					break
				}
			}
		}
	}

	return imports
}

func detectCSSSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
