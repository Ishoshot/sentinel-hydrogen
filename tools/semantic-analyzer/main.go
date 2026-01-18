package main

import (
	"bufio"
	"encoding/json"
	"fmt"
	"os"

	"github.com/sentinel/tools/semantic-analyzer/analyzer"
	"github.com/sentinel/tools/semantic-analyzer/types"
)

type Input struct {
	Filename  string `json:"filename"`
	Content   string `json:"content"`
	Extension string `json:"extension"`
}

func main() {
	reader := bufio.NewReader(os.Stdin)

	// Read JSON input from stdin
	var input Input
	decoder := json.NewDecoder(reader)
	if err := decoder.Decode(&input); err != nil {
		outputError(fmt.Sprintf("failed to parse input: %v", err))
		return
	}

	// Analyze the file
	result, err := analyzer.Analyze(input.Content, input.Filename, input.Extension)
	if err != nil {
		outputError(fmt.Sprintf("analysis failed: %v", err))
		return
	}

	// Output JSON result
	encoder := json.NewEncoder(os.Stdout)
	encoder.SetIndent("", "  ")
	encoder.Encode(result)
}

func outputError(msg string) {
	result := types.SemanticAnalysis{
		Errors: []types.SyntaxError{{Message: msg}},
	}
	json.NewEncoder(os.Stdout).Encode(result)
}
