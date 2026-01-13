package types

// SemanticAnalysis is the output structure returned to PHP
type SemanticAnalysis struct {
	Language  string         `json:"language"`
	Functions []FunctionInfo `json:"functions"`
	Classes   []ClassInfo    `json:"classes"`
	Imports   []ImportInfo   `json:"imports"`
	Exports   []ExportInfo   `json:"exports"`
	Calls     []CallInfo     `json:"calls"`
	Symbols   []SymbolInfo   `json:"symbols"`
	Errors    []SyntaxError  `json:"errors"`
}

type FunctionInfo struct {
	Name       string          `json:"name"`
	LineStart  int             `json:"line_start"`
	LineEnd    int             `json:"line_end"`
	Parameters []ParameterInfo `json:"parameters"`
	ReturnType string          `json:"return_type,omitempty"`
	Visibility string          `json:"visibility,omitempty"`
	IsAsync    bool            `json:"is_async"`
	IsStatic   bool            `json:"is_static"`
	Docstring  string          `json:"docstring,omitempty"`
}

type ClassInfo struct {
	Name       string         `json:"name"`
	LineStart  int            `json:"line_start"`
	LineEnd    int            `json:"line_end"`
	Extends    string         `json:"extends,omitempty"`
	Implements []string       `json:"implements"`
	Methods    []FunctionInfo `json:"methods"`
	Properties []PropertyInfo `json:"properties"`
}

type CallInfo struct {
	CallerFunction string `json:"caller_function,omitempty"`
	Callee         string `json:"callee"`
	Line           int    `json:"line"`
	ArgumentsCount int    `json:"arguments_count"`
	IsMethodCall   bool   `json:"is_method_call"`
	Receiver       string `json:"receiver,omitempty"`
}

type ImportInfo struct {
	Module    string   `json:"module"`
	Symbols   []string `json:"symbols"`
	Line      int      `json:"line"`
	IsDefault bool     `json:"is_default"`
}

type ExportInfo struct {
	Name string `json:"name"`
	Line int    `json:"line"`
}

type SymbolInfo struct {
	Name string `json:"name"`
	Kind string `json:"kind"`
	Line int    `json:"line"`
}

type ParameterInfo struct {
	Name string `json:"name"`
	Type string `json:"type,omitempty"`
}

type PropertyInfo struct {
	Name       string `json:"name"`
	Type       string `json:"type,omitempty"`
	Visibility string `json:"visibility,omitempty"`
}

type SyntaxError struct {
	Line    int    `json:"line"`
	Column  int    `json:"column"`
	Message string `json:"message"`
}
