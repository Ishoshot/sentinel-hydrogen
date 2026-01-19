# Code Quality Standards (Non-Negotiable)

## Performance & Scalability
- All code must scale to millions of records. Always assume high-volume production data.
- NEVER load unbounded data into memory. No `->get()` on queries without strict limits.
- NEVER perform in-memory grouping, sorting, or filtering when the database can do it.
- NEVER implement manual pagination. Use database-level `LIMIT/OFFSET` via `->paginate()` or `->cursorPaginate()`.
- Use database indexes, `GROUP BY`, window functions, and subqueries for aggregations.
- For "latest per group" patterns, use `ROW_NUMBER()` window functions or correlated subqueries.

## Code Cleanliness
- No spaghetti code. Each method should have a single, clear responsibility.
- No tech debt comments like "optimize later" - do it right the first time.
- Controllers must be thin - delegate to Actions for business logic.
- Complex queries should be extracted to dedicated query classes or scopes.

## Database Operations
- All queries must be workspace-scoped (per DATA_MODEL.md).
- Use eager loading to prevent N+1 queries.
- Use `->select()` to fetch only needed columns.
- Add database indexes for frequently filtered/sorted columns.
- Test queries with `EXPLAIN` for performance.

## Testing
- All code must have tests proving correctness.
- Include tests with large datasets to verify performance doesn't degrade.

## Review Checklist Before Submitting Code
1. Does this work with 1M+ records?
2. Is the memory usage bounded?
3. Is pagination happening at the database level?
4. Are all aggregations done in SQL, not PHP?
5. Would I pay for this code quality? If not, refactor.
