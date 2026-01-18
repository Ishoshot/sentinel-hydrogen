package languages

import (
	sitter "github.com/smacker/go-tree-sitter"
)

// WalkTree recursively walks the AST and calls the callback for each node
func WalkTree(node *sitter.Node, callback func(*sitter.Node)) {
	if node == nil {
		return
	}
	callback(node)
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil {
			WalkTree(child, callback)
		}
	}
}

// FindNodes finds all nodes of the given types
func FindNodes(root *sitter.Node, types []string) []*sitter.Node {
	var nodes []*sitter.Node
	typeSet := make(map[string]bool)
	for _, t := range types {
		typeSet[t] = true
	}

	WalkTree(root, func(node *sitter.Node) {
		if typeSet[node.Type()] {
			nodes = append(nodes, node)
		}
	})

	return nodes
}

// GetNodeText extracts the text content of a node
func GetNodeText(node *sitter.Node, source []byte) string {
	if node == nil {
		return ""
	}
	return string(source[node.StartByte():node.EndByte()])
}

// FindChildByType finds the first child node with the given type
func FindChildByType(node *sitter.Node, nodeType string) *sitter.Node {
	if node == nil {
		return nil
	}
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil && child.Type() == nodeType {
			return child
		}
	}
	return nil
}

// FindChildrenByType finds all child nodes with the given type
func FindChildrenByType(node *sitter.Node, nodeType string) []*sitter.Node {
	if node == nil {
		return nil
	}
	var children []*sitter.Node
	for i := 0; i < int(node.ChildCount()); i++ {
		child := node.Child(i)
		if child != nil && child.Type() == nodeType {
			children = append(children, child)
		}
	}
	return children
}

// CountArguments counts the number of arguments in a function call
func CountArguments(argsNode *sitter.Node) int {
	if argsNode == nil {
		return 0
	}
	count := 0
	for i := 0; i < int(argsNode.ChildCount()); i++ {
		child := argsNode.Child(i)
		if child != nil && child.Type() != "," && child.Type() != "(" && child.Type() != ")" {
			count++
		}
	}
	return count
}
