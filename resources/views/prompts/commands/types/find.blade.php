
## Task: FIND

Your task is to find all usages and references of a target (class, method, function, variable, pattern).

### What to Find

1. **Definition** - Where the target is defined
2. **Direct Usages** - All places it's directly used
3. **Indirect References** - Aliases, re-exports, inheritance
4. **Patterns** - How it's typically used

### Tool Strategy

1. **Start with definition**: Use `find_symbol` to locate where the target is defined
2. **Search for usages**: Use `search_pattern` with multiple patterns:
   - Class: `ClassName::`, `new ClassName`, `extends ClassName`, `implements ClassName`
   - Method: `->methodName(`, `::methodName(`
   - Function: `functionName(`
   - Variable: `$variableName`
3. **Verify context**: Use `read_file` to confirm each usage and understand context
4. **Find indirect references**: Use `search_code` for semantic matches that might use aliases

### Response Format

```
## Find: `[Target Name]`

### Definition
**Location:** `path/file.php:L42`
**Type:** [Class/Method/Function/Variable/Constant]
```[language]
// Definition snippet
```

### Usages Found: [count]

#### [Usage Category 1] ([count])

| File | Line | Context |
|------|------|---------|
| `path/file1.php` | 42 | [Brief description] |
| `path/file2.php` | 58 | [Brief description] |

#### [Usage Category 2] ([count])
...

### Usage Patterns

**Common patterns observed:**
- [Pattern 1 with example]
- [Pattern 2 with example]

### Dependencies

**This depends on:**
- `Dependency1` - [relationship]

**Depends on this:**
- `Dependent1` - [how it uses this]

### Search Queries Used
- `search_pattern`: `[pattern1]` - [X results]
- `search_pattern`: `[pattern2]` - [Y results]
```

### Guidelines

- Be exhaustive - search with multiple patterns to catch all usages
- Categorize usages (instantiation, method calls, inheritance, type hints, etc.)
- Show the search patterns you used so users can refine if needed
- If there are many usages, group by file or usage type
- Note any usages that look problematic or inconsistent
- If no usages found, confirm the definition exists and report that it appears unused
