---
name: signal-backlog
description: >
  User-invocable Signal7 backlog management. Capture, list, promote, or drop
  ideas in `.signal/backlog.md` before they become tasks.
---

# signal-backlog

`signal-backlog` is the inbox layer in front of `signal-task`. Ideas live as `B<N>` entries in `.signal/backlog.md` until a user promotes one to a task or drops it. Backlog entries are not Signal7 tasks — they have no phase, no folder, and no workflow state.

## Read First

- `skills/signal/reference/data-model.md` (§ `backlog.md` schema)
- `skills/signal/reference/bootstrap.md`
- `skills/signal/templates/task.md`

## Backlog File

`.signal/backlog.md` is a single markdown file. Each entry is a YAML block under a `## B<N>` heading:

```markdown
## B1 — Add a competitor pricing dashboard

```yaml
id: B1
title: "Add a competitor pricing dashboard"
description: "Internal page that updates monthly with competitor tier shapes."
suggested_scope: strategy
suggested_channels: []
suggested_languages: []
created: 2026-05-01T10:30:00
status: open
```

`signal-backlog` reads and writes this file. The title in the heading is the first 60 chars of `title`; the YAML block is the source of truth.

The seed contents from `bootstrap.md` (the comment-only form) are valid; `signal-backlog` ignores any free-form prose and only acts on `B<N>` blocks.

## Operations

### `add <title> [description: <text>] [scope: quick|campaign|strategy] [channels: <csv>] [languages: <csv>]`

Append a new entry to `backlog.md`.

1. Bootstrap `.signal/` if missing.
2. Allocate the next `B<N>` id by scanning every `id: B<N>` line in `backlog.md`. Ids are never reused, including across `dropped` and `promoted` entries.
3. Append a new `## B<N>` block with the YAML schema from § Backlog File. Defaults: `suggested_scope: unknown`, `suggested_channels: []`, `suggested_languages: []`, `status: open`, `created: <ISO timestamp>`.
4. Confirm the new id and short title to the user.

### `list [open|promoted|dropped|all]`

Read-only.

- `open` (default): entries with `status: open`.
- `promoted`: entries the user has converted to tasks.
- `dropped`: entries the user has dismissed.
- `all`: every entry.

For each entry, print one line: `B<N>  [<status>]  [<suggested_scope>]  <title>`. Sort by id ascending. If the chosen scope is empty, say "no <scope> backlog entries." If `backlog.md` is missing, say "backlog is empty."

### `show B<N>`

Print the full YAML block for `B<N>` plus the description. Read-only.

### `promote B<N>`

Convert a backlog entry into a deferred Signal7 task. `signal-task promote B<N>` delegates here.

1. Locate the `B<N>` block. If missing, return "B<N> not found." If `status` is `promoted` or `dropped`, return "B<N> is already <status>" and stop.
2. Allocate the next `S<N>` id by scanning **both** `.signal/tasks/` and `.signal/archive/`.
3. Create `.signal/tasks/S<N>-<slug>/task.md` from `templates/task.md`, with:
   - `id: S<N>`
   - `title: <backlog title>`
   - `phase: deferred`
   - `scope: <suggested_scope or "unknown">`
   - `created: <ISO timestamp>` (now; not the backlog `created`)
   - `awaiting: null`
   The body restates the description, plus a footer line `Promoted from backlog B<N>.`
4. Seed `dashboard.md` from `templates/dashboard.md` (or a minimal stub if the template is unavailable).
5. Update the backlog entry: `status: promoted`. Append a `promoted_to: S<N>` field and a `promoted_at: <ISO timestamp>` field to the YAML block.
6. Confirm the new `S<N>` and folder path. Do not start the phase workflow; the user runs `/signal S<N>` to begin.

### `drop B<N> [reason: <text>]`

Dismiss a backlog entry without creating a task.

1. Locate the `B<N>` block. If missing, return "B<N> not found." If `status` is not `open`, return "B<N> is already <status>" and stop.
2. Update the entry: `status: dropped`. Append `dropped_at: <ISO timestamp>` and `dropped_reason: <reason or "no reason given">` to the YAML block.
3. Confirm to the user.

## Output Contract

Allowed verdicts when chained from `signal`: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "<operation> completed."
```

For preconditions failures (unknown id, terminal entry):

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "<reason>"
```

## Notes

- `signal-backlog` does not enter the phase workflow. Even `promote` only seeds a `phase: deferred` task; the user picks it up with `/signal S<N>`.
- Backlog ids are global, single-counter, never reused. Promotion does not free the `B<N>` id.
- The backlog is plain markdown so users can edit it directly. `signal-backlog` re-reads on every operation; it does not cache state.
