package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/html"
)

// AnalyzeVue analyzes Vue SFC (Single File Component) source code
func AnalyzeVue(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)

	result := &types.SemanticAnalysis{
		Language:  "vue",
		Functions: []types.FunctionInfo{},
		Classes:   []types.ClassInfo{},
		Imports:   []types.ImportInfo{},
		Symbols:   []types.SymbolInfo{},
		Errors:    []types.SyntaxError{},
	}

	// Extract <script> content and analyze
	scriptContent, scriptLang, scriptStart := extractVueScript(sourceStr)
	if scriptContent != "" {
		var scriptAnalysis *types.SemanticAnalysis
		var err error

		if scriptLang == "ts" || scriptLang == "typescript" {
			scriptAnalysis, err = analyzeVueTypeScript([]byte(scriptContent))
		} else {
			scriptAnalysis, err = analyzeVueJavaScript([]byte(scriptContent))
		}

		if err == nil && scriptAnalysis != nil {
			// Adjust line numbers based on script position
			for _, f := range scriptAnalysis.Functions {
				f.LineStart += scriptStart
				f.LineEnd += scriptStart
				result.Functions = append(result.Functions, f)
			}
			for _, c := range scriptAnalysis.Classes {
				c.LineStart += scriptStart
				c.LineEnd += scriptStart
				result.Classes = append(result.Classes, c)
			}
			for _, i := range scriptAnalysis.Imports {
				i.Line += scriptStart
				result.Imports = append(result.Imports, i)
			}
		}
	}

	// Extract <template> and analyze for symbols (components, directives)
	templateContent, templateStart := extractVueTemplate(sourceStr)
	if templateContent != "" {
		templateSymbols := analyzeVueTemplate([]byte(templateContent), templateStart)
		result.Symbols = append(result.Symbols, templateSymbols...)
	}

	// Extract <style> blocks for CSS symbols
	styleContent, styleStart := extractVueStyle(sourceStr)
	if styleContent != "" {
		styleAnalysis, _ := AnalyzeCSS([]byte(styleContent))
		if styleAnalysis != nil {
			for _, s := range styleAnalysis.Symbols {
				s.Line += styleStart
				result.Symbols = append(result.Symbols, s)
			}
		}
	}

	return result, nil
}

// AnalyzeSvelte analyzes Svelte component source code
func AnalyzeSvelte(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)

	result := &types.SemanticAnalysis{
		Language:  "svelte",
		Functions: []types.FunctionInfo{},
		Classes:   []types.ClassInfo{},
		Imports:   []types.ImportInfo{},
		Symbols:   []types.SymbolInfo{},
		Errors:    []types.SyntaxError{},
	}

	// Extract <script> content and analyze
	scriptContent, scriptLang, scriptStart := extractSvelteScript(sourceStr)
	if scriptContent != "" {
		var scriptAnalysis *types.SemanticAnalysis
		var err error

		if scriptLang == "ts" || scriptLang == "typescript" {
			scriptAnalysis, err = analyzeVueTypeScript([]byte(scriptContent))
		} else {
			scriptAnalysis, err = analyzeVueJavaScript([]byte(scriptContent))
		}

		if err == nil && scriptAnalysis != nil {
			// Adjust line numbers
			for _, f := range scriptAnalysis.Functions {
				f.LineStart += scriptStart
				f.LineEnd += scriptStart
				result.Functions = append(result.Functions, f)
			}
			for _, c := range scriptAnalysis.Classes {
				c.LineStart += scriptStart
				c.LineEnd += scriptStart
				result.Classes = append(result.Classes, c)
			}
			for _, i := range scriptAnalysis.Imports {
				i.Line += scriptStart
				result.Imports = append(result.Imports, i)
			}
		}
	}

	// Extract <style> blocks
	styleContent, styleStart := extractSvelteStyle(sourceStr)
	if styleContent != "" {
		styleAnalysis, _ := AnalyzeCSS([]byte(styleContent))
		if styleAnalysis != nil {
			for _, s := range styleAnalysis.Symbols {
				s.Line += styleStart
				result.Symbols = append(result.Symbols, s)
			}
		}
	}

	return result, nil
}

func extractVueScript(source string) (content string, lang string, startLine int) {
	// Match <script> or <script setup> or <script lang="ts">
	scriptRegex := regexp.MustCompile(`(?is)<script(?:\s+setup)?(?:\s+lang=["']?(ts|typescript)["']?)?[^>]*>(.*?)</script>`)
	match := scriptRegex.FindStringSubmatchIndex(source)

	if len(match) < 6 {
		return "", "", 0
	}

	if match[2] != -1 && match[3] != -1 {
		lang = source[match[2]:match[3]]
	}

	if match[4] != -1 && match[5] != -1 {
		content = source[match[4]:match[5]]
		startLine = strings.Count(source[:match[4]], "\n")
	}

	return content, lang, startLine
}

func extractVueTemplate(source string) (content string, startLine int) {
	templateRegex := regexp.MustCompile(`(?is)<template[^>]*>(.*?)</template>`)
	match := templateRegex.FindStringSubmatchIndex(source)

	if len(match) < 4 {
		return "", 0
	}

	content = source[match[2]:match[3]]
	startLine = strings.Count(source[:match[2]], "\n")
	return content, startLine
}

func extractVueStyle(source string) (content string, startLine int) {
	styleRegex := regexp.MustCompile(`(?is)<style[^>]*>(.*?)</style>`)
	match := styleRegex.FindStringSubmatchIndex(source)

	if len(match) < 4 {
		return "", 0
	}

	content = source[match[2]:match[3]]
	startLine = strings.Count(source[:match[2]], "\n")
	return content, startLine
}

func extractSvelteScript(source string) (content string, lang string, startLine int) {
	// Match <script> or <script lang="ts">
	scriptRegex := regexp.MustCompile(`(?is)<script(?:\s+lang=["']?(ts|typescript)["']?)?[^>]*>(.*?)</script>`)
	match := scriptRegex.FindStringSubmatchIndex(source)

	if len(match) < 6 {
		return "", "", 0
	}

	if match[2] != -1 && match[3] != -1 {
		lang = source[match[2]:match[3]]
	}

	if match[4] != -1 && match[5] != -1 {
		content = source[match[4]:match[5]]
		startLine = strings.Count(source[:match[4]], "\n")
	}

	return content, lang, startLine
}

func extractSvelteStyle(source string) (content string, startLine int) {
	styleRegex := regexp.MustCompile(`(?is)<style[^>]*>(.*?)</style>`)
	match := styleRegex.FindStringSubmatchIndex(source)

	if len(match) < 4 {
		return "", 0
	}

	content = source[match[2]:match[3]]
	startLine = strings.Count(source[:match[2]], "\n")
	return content, startLine
}

func analyzeVueJavaScript(source []byte) (*types.SemanticAnalysis, error) {
	return AnalyzeJavaScript(source, "javascript")
}

func analyzeVueTypeScript(source []byte) (*types.SemanticAnalysis, error) {
	return AnalyzeTypeScript(source, "typescript")
}

func analyzeVueTemplate(source []byte, startLine int) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	parser := sitter.NewParser()
	parser.SetLanguage(html.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return symbols
	}
	defer tree.Close()

	root := tree.RootNode()

	// Find Vue components (PascalCase elements)
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

		// Check if it's a component (PascalCase or kebab-case with capital)
		if isPascalCase(tagName) || isKebabCaseComponent(tagName) {
			symbols = append(symbols, types.SymbolInfo{
				Name: tagName,
				Kind: "component",
				Line: int(element.StartPoint().Row) + 1 + startLine,
			})
		}

		// Extract v-directives
		attrs := FindNodes(startTag, []string{"attribute"})
		for _, attr := range attrs {
			nameNode := FindChildByType(attr, "attribute_name")
			if nameNode == nil {
				continue
			}

			attrName := GetNodeText(nameNode, source)
			if strings.HasPrefix(attrName, "v-") || strings.HasPrefix(attrName, "@") || strings.HasPrefix(attrName, ":") {
				symbols = append(symbols, types.SymbolInfo{
					Name: attrName,
					Kind: "directive",
					Line: int(attr.StartPoint().Row) + 1 + startLine,
				})
			}
		}
	}

	return symbols
}

func isPascalCase(s string) bool {
	if len(s) == 0 {
		return false
	}
	// First character must be uppercase
	if s[0] < 'A' || s[0] > 'Z' {
		return false
	}
	// Must have at least one lowercase
	hasLower := false
	for _, c := range s {
		if c >= 'a' && c <= 'z' {
			hasLower = true
			break
		}
	}
	return hasLower
}

func isKebabCaseComponent(s string) bool {
	// Custom components often use kebab-case but start with a capital letter
	// or have specific prefixes like "my-", "app-", etc.
	return strings.Contains(s, "-") && !strings.HasPrefix(s, "v-")
}
