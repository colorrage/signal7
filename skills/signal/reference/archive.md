# Signal7 Archive

Terminal task folders move from `.signal/tasks/` to `.signal/archive/`.

Terminal phases:

- `done`
- `cancelled`

Ids are never reused. New task id allocation scans both `.signal/tasks/` and `.signal/archive/`, finds the highest `S<N>`, and creates `S<N+1>`.

## Archive Contract

Only orchestrators or management skills move folders:

- `signal` archives phase-driven `done` tasks.
- `signal-task` archives explicit cancellation.

Phase skills and workers never move task folders.

If the destination exists, stop and surface a blocked state instead of overwriting.
