# Signal7 Archive

Terminal task folders move from `.signal/tasks/` to `.signal/archive/`.

Terminal phases:

- `done`
- `cancelled`

Ids are never reused. New task id allocation scans both `.signal/tasks/` and `.signal/archive/`, finds the highest `S<N>`, and creates `S<N+1>`.

## Archive Contract

Only orchestrators or management skills move folders:

- `signal` archives phase-driven `done` tasks.
- `signal-task` archives explicit cancellation (`cancel`). `defer` does **not** archive — deferred tasks stay under `.signal/tasks/` so they can be resumed.

Phase skills and workers never move task folders.

If the destination already contains a folder for the same `S<N>`, stop and surface a blocked state instead of overwriting. (`S<N>` ids are unique by allocation rule, so a collision is a sign of state corruption rather than a routine condition.)

## Resume Behaviour

Archived tasks are **read-only references**, not resumable workflows. `signal` may locate a task in `.signal/archive/` to report status or read context, but it must not move an archived folder back into `.signal/tasks/` or restart its phase. A user wanting to redo an archived task creates a new `S<N>` and links to the archived one in the body if relevant.

This contract supersedes any older language in `signal/SKILL.md` that suggested archived tasks could be resumed.
