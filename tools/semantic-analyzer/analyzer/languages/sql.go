package languages

import (
	"regexp"
	"strings"

	"github.com/sentinel/tools/semantic-analyzer/types"
)

// AnalyzeSQL analyzes SQL source code and extracts semantic information
// Uses regex-based parsing as tree-sitter SQL support varies
func AnalyzeSQL(source []byte) (*types.SemanticAnalysis, error) {
	sourceStr := string(source)
	lines := strings.Split(sourceStr, "\n")

	result := &types.SemanticAnalysis{
		Language: "sql",
		Symbols:  extractSQLSymbols(sourceStr, lines),
		Calls:    extractSQLCalls(sourceStr, lines),
		Errors:   []types.SyntaxError{},
	}

	return result, nil
}

func extractSQLSymbols(source string, lines []string) []types.SymbolInfo {
	var symbols []types.SymbolInfo

	// Extract CREATE TABLE statements
	tableRegex := regexp.MustCompile(`(?i)CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matches := tableRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			tableName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: tableName,
				Kind: "table",
				Line: line,
			})
		}
	}

	// Extract CREATE INDEX statements
	indexRegex := regexp.MustCompile(`(?i)CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matches = indexRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			indexName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: indexName,
				Kind: "index",
				Line: line,
			})
		}
	}

	// Extract CREATE VIEW statements
	viewRegex := regexp.MustCompile(`(?i)CREATE\s+(?:OR\s+REPLACE\s+)?VIEW\s+["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matches = viewRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			viewName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: viewName,
				Kind: "view",
				Line: line,
			})
		}
	}

	// Extract CREATE FUNCTION/PROCEDURE statements
	funcRegex := regexp.MustCompile(`(?i)CREATE\s+(?:OR\s+REPLACE\s+)?(?:FUNCTION|PROCEDURE)\s+["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matches = funcRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			funcName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: funcName,
				Kind: "function",
				Line: line,
			})
		}
	}

	// Extract CREATE TRIGGER statements
	triggerRegex := regexp.MustCompile(`(?i)CREATE\s+(?:OR\s+REPLACE\s+)?TRIGGER\s+["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matches = triggerRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matches {
		if len(match) >= 4 {
			triggerName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			symbols = append(symbols, types.SymbolInfo{
				Name: triggerName,
				Kind: "trigger",
				Line: line,
			})
		}
	}

	return symbols
}

func extractSQLCalls(source string, lines []string) []types.CallInfo {
	var calls []types.CallInfo

	// Extract SELECT statements (as queries)
	selectRegex := regexp.MustCompile(`(?i)\bSELECT\b`)
	matches := selectRegex.FindAllStringIndex(source, -1)
	for _, match := range matches {
		line := countLines(source[:match[0]]) + 1
		calls = append(calls, types.CallInfo{
			Callee: "SELECT",
			Line:   line,
		})
	}

	// Extract INSERT statements
	insertRegex := regexp.MustCompile(`(?i)\bINSERT\s+INTO\s+["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matchesInsert := insertRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matchesInsert {
		if len(match) >= 4 {
			tableName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			calls = append(calls, types.CallInfo{
				Callee:   "INSERT",
				Line:     line,
				Receiver: tableName,
			})
		}
	}

	// Extract UPDATE statements
	updateRegex := regexp.MustCompile(`(?i)\bUPDATE\s+["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matchesUpdate := updateRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matchesUpdate {
		if len(match) >= 4 {
			tableName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			calls = append(calls, types.CallInfo{
				Callee:   "UPDATE",
				Line:     line,
				Receiver: tableName,
			})
		}
	}

	// Extract DELETE statements
	deleteRegex := regexp.MustCompile(`(?i)\bDELETE\s+FROM\s+["\` + "`" + `]?(\w+)["\` + "`" + `]?`)
	matchesDelete := deleteRegex.FindAllStringSubmatchIndex(source, -1)
	for _, match := range matchesDelete {
		if len(match) >= 4 {
			tableName := source[match[2]:match[3]]
			line := countLines(source[:match[0]]) + 1
			calls = append(calls, types.CallInfo{
				Callee:   "DELETE",
				Line:     line,
				Receiver: tableName,
			})
		}
	}

	return calls
}

func countLines(s string) int {
	return strings.Count(s, "\n")
}
