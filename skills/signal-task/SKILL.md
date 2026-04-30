---
name: signal-task
description: >
  User-invocable Signal7 task management. Phase 1 ships two operations:
  cancel a task (terminal, archives the folder) and report status (read-only).
  Other operations — list, defer, create-deferred, promote-from-backlog — are
  planned management work.
---

# signal-task

`signal-task` manages tasks outside the phase workflow. It is the only skill besides `signal` that writes `task.md`. Phase 1 ships **cancel** and **status** only; all other operations refuse with a clear "planned, not yet implemented" message.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/archive.md`
- `skills/signal/reference/bootstrap.md`

## Operations

### `cancel S<N> [reason: <text>]`

Terminal cancellation. Sets `phase: cancelled`, writes a cancellation reason, and archives the folder.

1. Locate `.signal/tasks/S<N>-*/`. If only `.signal/archive/` has it, report "S<N> is already archived (`<phase>`)" and stop.
2. Read `task.md` frontmatter. If `phase: done` or `phase: cancelled`, report and stop without rewriting.
3. Bootstrap `.signal/archive/` if missing (`mkdir -p`).
4. Update `task.md`:
   - `phase: cancelled`
   - `awaiting: null`
   - append a `## Cancellation` section to the body with `Date: <ISO timestamp>` and `Reason: <reason or "no reason given">`.
5. Append a `## Decisions` entry to `dashboard.md`: `- <ISO> - user - cancelled S<N>: <reason>`.
6. Move `.signal/tasks/S<N>-*` to `.signal/archive/S<N>-*`. If the destination already contains a folder for the same id, surface a blocked state and stop without overwriting (per `archive.md`).
7. Confirm cancellation to the user with the destination path.

### `status [S<N>]`

Read-only summary. Bootstrap is **not** required (read-side per `bootstrap.md`).

If `S<N>` is given:

1. Locate the task in `.signal/tasks/S<N>-*/` first, then `.signal/archive/S<N>-*/`. If neither, report "S<N> not found" and stop.
2. Read `task.md` frontmatter (`id`, `title`, `phase`, `scope`, `awaiting`, `created`).
3. Read `dashboard.md` § Status if present.
4. Report a one-paragraph summary: id, title, phase, scope, awaiting (if any), location (active vs archived), created date.

If `S<N>` is omitted:

1. Treat as "list active tasks." This stays narrow in Phase 1 — list only `.signal/tasks/*/task.md` (not archive).
2. For each, print one line: `S<N>  [<phase>]  <awaiting-or-"-">  <title>`.
3. If `.signal/tasks/` is empty, say "no active tasks."

### Other operations (planned)

`list` (full, including archive), `defer`, `create-deferred`, `promote` (from backlog) are not implemented in Phase 1. Calls return:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "signal-task <operation> is planned and is not yet implemented."
```

## Output Contract

`signal-task` is user-invocable but participates in the verdict protocol when chained from `signal`. Allowed verdicts: `phase-complete`, `awaiting-input`.

After a successful `cancel`:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "S<N> cancelled and archived."
```

After `status`, no verdict is required when invoked directly by the user; return the human-readable summary only. When chained from `signal`, return:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Status reported."
```

For unimplemented management operations, return the `awaiting-input` form above.

## Independence

`signal-task` does not read Hyper7 internals. Cross-system references in cancellation reasons or status output are user-supplied free text, not parsed.
