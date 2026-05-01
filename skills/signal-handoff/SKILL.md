---
name: signal-handoff
description: >
  User-invocable Signal7 handoff writer. Captures the context another session
  needs to resume an in-flight task: decisions, ruled-out paths, uncommitted
  work, open questions, and the immediate next step.
---

# signal-handoff

`signal-handoff` writes `handoff.md` into the current task folder so a future session — same person, different person, different agent — can resume without re-deriving context. Human-triggered only. Phase skills do not call this.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/bootstrap.md`

## When to Run

Use `signal-handoff` when:

- the user wants to pause a long-running task mid-flight,
- a session is ending and there is uncommitted decision context not captured in `brief.md` / `content-plan.md` / `review.md`,
- the task will be picked up by a different person or agent,
- a blocking external event means the next step is "wait for X then do Y."

Do not run automatically on every phase return — phase artifacts already capture in-phase state.

## Operation

### `signal-handoff [S<N>]`

If `S<N>` is omitted, target the single active task in `.signal/tasks/`. If there are zero or more than one active tasks, ask the user which one.

1. Locate `.signal/tasks/S<N>-*/`. Refuse to write into `.signal/archive/` (archived tasks are read-only references, per `archive.md`).
2. Read `task.md` frontmatter (id, title, phase, awaiting) and any existing `handoff.md`.
3. If `handoff.md` exists, do not overwrite. Append a new dated section instead. Multiple handoffs over a long task are valid history.
4. Ask the user to fill (or confirm) each section before writing — `signal-handoff` is a structured capture, not a content generator. Sections:
   - **Where this stands.** One paragraph. Phase, what's done, what's blocking.
   - **Decisions made this session.** Bullets. Things resolved in conversation that are not visible in the artifacts on disk.
   - **Paths already ruled out.** Bullets. Approaches considered and rejected, with the reason.
   - **Uncommitted work.** Bullets. Files / drafts / partial generations the user knows about that are not yet on disk.
   - **Open questions.** Bullets. Things still unresolved that the next session must answer.
   - **Next step.** One sentence. The single concrete action the next session should take.
5. Write the handoff under a dated heading:

   ```markdown
   ## Handoff — 2026-05-01T15:42:00

   ### Where this stands
   …

   ### Decisions made this session
   - …

   …
   ```
6. Append a `## Decisions` entry to `dashboard.md`: `- <ISO> - user - handoff written: <one-line summary>`.
7. Confirm to the user, naming the file path.

## Output Contract

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Handoff written for S<N>."
```

If the active task cannot be resolved or the user declines to fill required sections:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "<reason>"
```

## Notes

- `signal-handoff` does not change `phase` or `awaiting`. To park the task as well, run `signal-task defer S<N>` after the handoff.
- The handoff is a markdown artifact, not parsed by other skills. `signal` may show its existence to a returning session, but does not consume its content.
