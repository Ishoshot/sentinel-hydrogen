---
trigger: always_on
---

# Code Review & Quality

## Self-Review Before Completion

After writing or modifying code, **pause and critically review your own work** before considering the task done. Do not assume correctness — verify it. Specifically check for:

- **Logic bugs:** Does every method do exactly what its name and docblock promise? Trace through edge cases mentally.
- **Blast radius:** Does a method that should affect one record accidentally affect many? Does a query scope filter tightly enough?
- **Dependency correctness:** When action A calls action B, does B's behavior match what A expects in all cases? Look for mismatches in scope, side effects, or return values.
- **Race conditions:** If this code runs concurrently (e.g., queued jobs, webhook retries), does it still behave correctly?
- **State transitions:** Are only the intended records transitioned? Could adjacent or unrelated records be caught in the blast?

If you find an issue during self-review, fix it immediately — do not leave it for the user to catch.

## Code Review (Required)

Before considering any code-writing task complete, invoke the **code-reviewer** agent to review your changes. This applies when you've created new files or made non-trivial edits.

**Review cycle:**

1. Run the code-reviewer agent after completing your changes
2. If the reviewer identifies issues that need fixing, fix them
3. Re-run the code-reviewer to verify the fixes are correct (very important)
4. Repeat until the reviewer passes with no critical issues
5. It is important that you check in with the reviewer after addressing issues it raised (do not ignore this)
6. If you're running in claude code web/remote ensure this is done before pushing changes to git remote
7. When re-running the code-reviewer to verify fixes, if you attempt to resume the agent and you get an error, spawn a new agent instead

The task is only complete when the code-reviewer confirms the code is acceptable.
