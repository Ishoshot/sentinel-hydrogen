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
	// Core languages with full tree-sitter support
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

	// JVM languages
	case "java":
		return languages.AnalyzeJava([]byte(content))
	case "kotlin":
		return languages.AnalyzeKotlin([]byte(content))

	// .NET
	case "csharp":
		return languages.AnalyzeCSharp([]byte(content))

	// Dynamic languages
	case "ruby":
		return languages.AnalyzeRuby([]byte(content))

	// Apple ecosystem
	case "swift":
		return languages.AnalyzeSwift([]byte(content))

	// Systems languages
	case "c":
		return languages.AnalyzeC([]byte(content))
	case "cpp":
		return languages.AnalyzeCPP([]byte(content))

	// Frontend frameworks
	case "vue":
		return languages.AnalyzeVue([]byte(content))
	case "svelte":
		return languages.AnalyzeSvelte([]byte(content))

	// Web fundamentals
	case "html":
		return languages.AnalyzeHTML([]byte(content))
	case "css":
		return languages.AnalyzeCSS([]byte(content))
	case "scss":
		return languages.AnalyzeSCSS([]byte(content))

	// Data & config
	case "sql":
		return languages.AnalyzeSQL([]byte(content))
	case "yaml":
		return languages.AnalyzeYAML([]byte(content))

	// Shell
	case "bash":
		return languages.AnalyzeBash([]byte(content))

	// JVM languages (additional)
	case "scala":
		return languages.AnalyzeScala([]byte(content))
	case "groovy":
		return languages.AnalyzeGroovy([]byte(content))
	case "clojure":
		return languages.AnalyzeClojure([]byte(content))

	// Functional languages
	case "elixir":
		return languages.AnalyzeElixir([]byte(content))
	case "haskell":
		return languages.AnalyzeHaskell([]byte(content))
	case "ocaml":
		return languages.AnalyzeOCaml([]byte(content))
	case "fsharp":
		return languages.AnalyzeFSharp([]byte(content))

	// Scripting languages
	case "lua":
		return languages.AnalyzeLua([]byte(content))
	case "perl":
		return languages.AnalyzePerl([]byte(content))
	case "r":
		return languages.AnalyzeR([]byte(content))
	case "julia":
		return languages.AnalyzeJulia([]byte(content))

	// Mobile/Systems
	case "dart":
		return languages.AnalyzeDart([]byte(content))
	case "objc":
		return languages.AnalyzeObjectiveC([]byte(content))
	case "zig":
		return languages.AnalyzeZig([]byte(content))

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
		// Core languages
		"php": "php",
		"js":  "javascript",
		"mjs": "javascript",
		"cjs": "javascript",
		"jsx": "jsx",
		"ts":  "typescript",
		"tsx": "tsx",
		"py":  "python",
		"go":  "go",
		"rs":  "rust",

		// JVM languages
		"java": "java",
		"kt":   "kotlin",
		"kts":  "kotlin",

		// .NET
		"cs": "csharp",

		// Dynamic languages
		"rb": "ruby",

		// Apple ecosystem
		"swift": "swift",

		// Systems languages
		"c":   "c",
		"h":   "c",
		"cpp": "cpp",
		"cc":  "cpp",
		"cxx": "cpp",
		"hpp": "cpp",
		"hxx": "cpp",

		// Frontend frameworks
		"vue":    "vue",
		"svelte": "svelte",

		// Web fundamentals
		"html": "html",
		"htm":  "html",
		"css":  "css",
		"scss": "scss",
		"sass": "scss",

		// Data & config
		"sql":  "sql",
		"yaml": "yaml",
		"yml":  "yaml",

		// Shell
		"sh":   "bash",
		"bash": "bash",
		"zsh":  "bash",

		// JVM languages (additional)
		"scala": "scala",
		"sc":    "scala",
		"groovy": "groovy",
		"gvy":    "groovy",
		"gy":     "groovy",
		"gsh":    "groovy",
		"clj":    "clojure",
		"cljs":   "clojure",
		"cljc":   "clojure",
		"edn":    "clojure",

		// Functional languages
		"ex":   "elixir",
		"exs":  "elixir",
		"hs":   "haskell",
		"lhs":  "haskell",
		"ml":   "ocaml",
		"mli":  "ocaml",
		"fs":   "fsharp",
		"fsi":  "fsharp",
		"fsx":  "fsharp",

		// Scripting languages
		"lua":  "lua",
		"pl":   "perl",
		"pm":   "perl",
		"t":    "perl",
		"r":    "r",
		"R":    "r",
		"jl":   "julia",

		// Mobile/Systems
		"dart": "dart",
		"m":    "objc",
		"mm":   "objc",
		"zig":  "zig",
	}

	if language, ok := extensionMap[ext]; ok {
		return language
	}

	return ""
}
