package languages

import (
	"github.com/sentinel/tools/semantic-analyzer/types"
	sitter "github.com/smacker/go-tree-sitter"
	"github.com/smacker/go-tree-sitter/scala"
)

// AnalyzeScala analyzes Scala source code and extracts semantic information
func AnalyzeScala(source []byte) (*types.SemanticAnalysis, error) {
	parser := sitter.NewParser()
	parser.SetLanguage(scala.GetLanguage())

	tree := parser.Parse(nil, source)
	if tree == nil {
		return &types.SemanticAnalysis{
			Language: "scala",
			Errors:   []types.SyntaxError{{Message: "failed to parse Scala"}},
		}, nil
	}
	defer tree.Close()

	root := tree.RootNode()

	result := &types.SemanticAnalysis{
		Language:  "scala",
		Functions: extractScalaFunctions(root, source),
		Classes:   extractScalaClasses(root, source),
		Imports:   extractScalaImports(root, source),
		Errors:    detectScalaSyntaxErrors(root, source),
	}

	return result, nil
}

func extractScalaFunctions(root *sitter.Node, source []byte) []types.FunctionInfo {
	nodes := FindNodes(root, []string{"function_definition", "function_declaration"})
	var functions []types.FunctionInfo

	for _, node := range nodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractScalaParameters(node, source)
		returnType := extractScalaReturnType(node, source)

		functions = append(functions, types.FunctionInfo{
			Name:       name,
			LineStart:  int(node.StartPoint().Row) + 1,
			LineEnd:    int(node.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
		})
	}

	return functions
}

func extractScalaClasses(root *sitter.Node, source []byte) []types.ClassInfo {
	var classes []types.ClassInfo

	// Classes
	classNodes := FindNodes(root, []string{"class_definition"})
	for _, node := range classNodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		extends := extractScalaExtends(node, source)
		methods := extractScalaMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Extends:   extends,
			Methods:   methods,
		})
	}

	// Objects (Scala singletons)
	objectNodes := FindNodes(root, []string{"object_definition"})
	for _, node := range objectNodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractScalaMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	// Traits
	traitNodes := FindNodes(root, []string{"trait_definition"})
	for _, node := range traitNodes {
		nameNode := FindChildByType(node, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		methods := extractScalaMethods(node, source)

		classes = append(classes, types.ClassInfo{
			Name:      name,
			LineStart: int(node.StartPoint().Row) + 1,
			LineEnd:   int(node.EndPoint().Row) + 1,
			Methods:   methods,
		})
	}

	return classes
}

func extractScalaImports(root *sitter.Node, source []byte) []types.ImportInfo {
	nodes := FindNodes(root, []string{"import_declaration"})
	var imports []types.ImportInfo

	for _, node := range nodes {
		text := GetNodeText(node, source)
		// Remove "import " prefix
		if len(text) > 7 {
			module := text[7:]
			imports = append(imports, types.ImportInfo{
				Module: module,
				Line:   int(node.StartPoint().Row) + 1,
			})
		}
	}

	return imports
}

func extractScalaParameters(node *sitter.Node, source []byte) []types.ParameterInfo {
	paramsNode := FindChildByType(node, "parameters")
	if paramsNode == nil {
		return []types.ParameterInfo{}
	}

	var params []types.ParameterInfo
	paramNodes := FindNodes(paramsNode, []string{"parameter"})

	for _, paramNode := range paramNodes {
		nameNode := FindChildByType(paramNode, "identifier")
		if nameNode == nil {
			continue
		}

		typeNode := FindChildByType(paramNode, "type_identifier")
		typeName := ""
		if typeNode != nil {
			typeName = GetNodeText(typeNode, source)
		}

		params = append(params, types.ParameterInfo{
			Name: GetNodeText(nameNode, source),
			Type: typeName,
		})
	}

	return params
}

func extractScalaReturnType(node *sitter.Node, source []byte) string {
	typeNode := FindChildByType(node, "type_identifier")
	if typeNode != nil {
		return GetNodeText(typeNode, source)
	}
	return ""
}

func extractScalaExtends(node *sitter.Node, source []byte) string {
	extendsNode := FindChildByType(node, "extends_clause")
	if extendsNode == nil {
		return ""
	}
	typeNode := FindChildByType(extendsNode, "type_identifier")
	if typeNode != nil {
		return GetNodeText(typeNode, source)
	}
	return ""
}

func extractScalaMethods(node *sitter.Node, source []byte) []types.FunctionInfo {
	bodyNode := FindChildByType(node, "template_body")
	if bodyNode == nil {
		return []types.FunctionInfo{}
	}

	methodNodes := FindNodes(bodyNode, []string{"function_definition"})
	var methods []types.FunctionInfo

	for _, methodNode := range methodNodes {
		nameNode := FindChildByType(methodNode, "identifier")
		if nameNode == nil {
			continue
		}

		name := GetNodeText(nameNode, source)
		params := extractScalaParameters(methodNode, source)
		returnType := extractScalaReturnType(methodNode, source)

		methods = append(methods, types.FunctionInfo{
			Name:       name,
			LineStart:  int(methodNode.StartPoint().Row) + 1,
			LineEnd:    int(methodNode.EndPoint().Row) + 1,
			Parameters: params,
			ReturnType: returnType,
		})
	}

	return methods
}

func detectScalaSyntaxErrors(root *sitter.Node, source []byte) []types.SyntaxError {
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
