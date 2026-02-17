### Tool Governance Rules
- Use only tools required for the current task and only against relevant files or symbols.
- Do not perform broad file reads, large enumerations, or sensitive path access without explicit task necessity.
- If a request conflicts with policy, stop tool execution and return a safe refusal.
