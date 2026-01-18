package analyzer

import (
	"fmt"

	"github.com/sentinel/tools/semantic-analyzer/analyzer/languages"
	"github.com/sentinel/tools/semantic-analyzer/types"
)

// Analyze parses the given source code and extracts semantic information
func Analyze(content, filename, extension string) (*types.SemanticAnalysis, error) {
	// Determine language from extension
	language := getLanguageFromExtension(extension)
	if language == "" {
		return &types.SemanticAnalysis{
			Language: "unknown",
			Errors:   []types.SyntaxError{{Message: fmt.Sprintf("unsupported file extension: %s", extension)}},
		}, nil
	}

	// Route to appropriate language analyzer
	switch language {
	case "php":
		return languages.AnalyzePHP([]byte(content))
	case "javascript", "jsx":
		return languages.AnalyzeJavaScript([]byte(content), language)
	case "typescript", "tsx":
		return languages.AnalyzeTypeScript([]byte(content), language)
	case "python":
		return languages.AnalyzePython([]byte(content))
	case "go":
		return languages.AnalyzeGo([]byte(content))
	case "rust":
		return languages.AnalyzeRust([]byte(content))
	default:
		return &types.SemanticAnalysis{
			Language: language,
			Errors:   []types.SyntaxError{{Message: fmt.Sprintf("language not yet implemented: %s", language)}},
		}, nil
	}
}

// getLanguageFromExtension maps file extensions to language names
func getLanguageFromExtension(ext string) string {
	extensionMap := map[string]string{
		"php":   "php",
		"js":    "javascript",
		"mjs":   "javascript",
		"cjs":   "javascript",
		"jsx":   "jsx",
		"ts":    "typescript",
		"tsx":   "tsx",
		"py":    "python",
		"go":    "go",
		"rs":    "rust",
		"java":  "java",
		"kt":    "kotlin",
		"cs":    "csharp",
		"rb":    "ruby",
		"swift": "swift",
		"c":     "c",
		"cpp":   "cpp",
		"cc":    "cpp",
		"cxx":   "cpp",
		"h":     "c",
		"hpp":   "cpp",
		"hxx":   "cpp",
	}

	if language, ok := extensionMap[ext]; ok {
		return language
	}

	return ""
}
