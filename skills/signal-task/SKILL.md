---
name: signal-task
description: >
  User-invocable Signal7 task management. Lists, statuses, defers, cancels,
  creates deferred tasks, and promotes backlog items to tasks. The only skill
  besides `signal` that writes top-level `task.md` fields.
---

# signal-task

`signal-task` manages tasks outside the phase workflow. It is the only skill besides `signal` that writes `task.md`. Phase 4 ships the full operation set: `list`, `status`, `cancel`, `defer`, `create-deferred`, and `promote`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/archive.md`
- `skills/signal/reference/bootstrap.md`

## Operations

### `list [active|archive|all]`

Read-only. Bootstrap is **not** required.

- `active` (default): list `.signal/tasks/*/task.md`.
- `archive`: list `.signal/archive/*/task.md`.
- `all`: union of both.

For each task, print one line: `S<N>  [<phase>]  <awaiting-or-"-">  <title>`. Sort by id ascending. If the chosen scope is empty, say "no <scope> tasks." If `.signal/` itself is missing, say "no Signal7 state in this project."

### `status [S<N>]`

Read-only. Bootstrap is **not** required.

If `S<N>` is given:

1. Locate the task in `.signal/tasks/S<N>-*/` first, then `.signal/archive/S<N>-*/`. If neither, report "S<N> not found" and stop.
2. Read `task.md` frontmatter (`id`, `title`, `phase`, `scope`, `awaiting`, `created`).
3. Read `dashboard.md` § Status if present.
4. Report a one-paragraph summary: id, title, phase, scope, awaiting (if any), location (active vs archived), created date.

If `S<N>` is omitted, behave like `list active`.

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

### `defer S<N> [reason: <text>]`

Park an active task for later. Does **not** archive — the folder stays under `.signal/tasks/`.

1. Locate `.signal/tasks/S<N>-*/`. If only `.signal/archive/` has it, report "S<N> is archived; defer is not allowed on archived tasks" and stop.
2. Read `task.md` frontmatter. If `phase: done`, `phase: cancelled`, or `phase: deferred`, report and stop without rewriting.
3. Update `task.md`:
   - `phase: deferred`
   - `awaiting: null`
   - append a `## Deferral` section to the body with `Date: <ISO timestamp>` and `Reason: <reason or "no reason given">`.
4. Append a `## Decisions` entry to `dashboard.md`: `- <ISO> - user - deferred S<N>: <reason>`.
5. Confirm to the user. The task can be resumed later by `/signal S<N>` (which will set `phase` to the appropriate first-active phase per `gates.md`).

### `create-deferred <title>`

Create a new task in `phase: deferred` without entering the phase workflow. Bootstraps `.signal/` if missing.

1. Allocate the next `S<N>` id by scanning **both** `.signal/tasks/` and `.signal/archive/` for the highest existing prefix.
2. Derive a kebab-case slug from the title (lowercase, ASCII letters/digits/hyphens, ~40 chars).
3. Create `.signal/tasks/S<N>-<slug>/task.md` from `templates/task.md`, replacing the placeholders:
   - `id: S<N>`
   - `title: <title>`
   - `phase: deferred`
   - `scope: unknown`
   - `created: <ISO timestamp>`
   - `awaiting: null`
4. Body: one paragraph restating the title in the user's words. The user (or `signal`) fills the rest later.
5. Seed `dashboard.md` from `templates/dashboard.md` if available; otherwise create a minimal stub (`## Goal` from the body, `## Status` showing phase + awaiting, other sections placeholder).
6. Confirm the new task id and folder path. Do not invoke `signal`.

### `promote B<N>` *(bridge to `signal-backlog`)*

Convert a backlog entry `B<N>` to an active task. The actual entry mutation lives in `signal-backlog promote`; `signal-task promote` is a delegating shortcut so users can promote from either skill.

1. Invoke `signal-backlog promote B<N>`. Forward its output to the user.
2. If `signal-backlog` reports "B<N> not found" or "B<N> already promoted/dropped," surface that message unchanged.

`signal-task` does not parse `backlog.md` directly. Promotion semantics — task seeding, id allocation, status flip on the backlog entry — live in `signal-backlog`.

## Output Contract

`signal-task` is user-invocable but participates in the verdict protocol when chained from `signal`. Allowed verdicts: `phase-complete`, `awaiting-input`.

After a successful state-changing operation (`cancel`, `defer`, `create-deferred`, `promote`):

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "<operation> completed."
```

After a read-only operation (`list`, `status`), no verdict is required when invoked directly by the user; return the human-readable summary only. When chained from `signal`, return:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Status reported."
```

If a precondition fails (id not found, terminal task, archive collision), return:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "<reason>"
```

## Independence

`signal-task` does not read Hyper7 internals. Cross-system references in cancellation, deferral, or task body text are user-supplied free text, not parsed.
