---
name: signal-retro
description: >
  User-invocable Signal7 retrospective writer. Captures what worked, what
  didn't, and what to do differently — scoped to a specific task or to the
  project overall.
---

# signal-retro

`signal-retro` records lessons from finished work. Two scopes: per-task (writes `retro.md` into the task folder) or project-level (appends to `.signal/retro.md`). Human-triggered only.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/bootstrap.md`

## When to Run

- After finishing a task that taught something non-obvious.
- At the end of a session where multiple tasks shipped and a roll-up makes sense.
- When Signal7 itself helped or got in the way in a place that deserves recording.

Do not run after every task. The signal is "I learned something I want a future session to know," not "the task is done."

## Operations

### `signal-retro task S<N>`

Per-task retrospective. Writes `retro.md` inside the task folder.

1. Locate the task in `.signal/tasks/S<N>-*/` first, then `.signal/archive/S<N>-*/`. Both are valid — retros on archived tasks are explicitly allowed (the archive is read-only for state mutation, but `retro.md` is reflection, not state).
2. If `retro.md` already exists in that folder, append a new dated section rather than overwriting.
3. Ask the user to fill three required sections, plus optional sections when relevant:
   - **What worked.** Bullets.
   - **What didn't.** Bullets.
   - **What to do differently next time.** Bullets — concrete, actionable.
   Optional sections (ask if the user doesn't raise them unprompted):
   - **About Signal7 itself.** When a skill got in the way or helped — the brief asked the wrong question, signal-plan mapped a channel incorrectly, signal-review let a broken claim through, signal-translate mangled a forbidden term. This is feedback on the skill code, not the business work. It tells the next editor of `skills/signal-*/SKILL.md` what to fix.
   - **About the project.** When something about the business context, brand guidelines, competitor data, or tooling surfaced during the task that should be noted for future Signal7 work.

   Separate observations by scope. "The brand.md tone was wrong for Instagram" is about the project. "signal-review didn't catch the claim conflict" is about Signal7 itself. Keep them honest — a project-level friction isn't a Signal7 problem.

4. Write under a dated heading:

   ```markdown
   ## Retro — 2026-05-01T16:10:00

   ### What worked
   - …

   ### What didn't
   - …

   ### What to do differently next time
   - …

   ### About Signal7 itself
   - …

   ### About the project
   - …
   ```
5. Confirm the file path to the user. Do not change task `phase` or `awaiting`.

### `signal-retro project`

Project-level retrospective. Appends to `.signal/retro.md`.

1. Bootstrap `.signal/` if missing.
2. If `.signal/retro.md` does not exist, create it with the heading:

   ```markdown
   # Signal7 Project Retrospectives
   ```
3. Append a new dated section with the same three required subsections (`What worked`, `What didn't`, `What to do differently next time`), plus the two optional sections (`About Signal7 itself`, `About the project`) when the conversation surfaces Signal7-skill or business-context friction worth recording.
4. Confirm to the user.

## Output Contract

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Retro written."
```

If the user does not provide content:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Retro empty; nothing written."
```

## Notes

- Retros are append-only. Earlier entries are preserved verbatim.
- No other Signal7 skill reads retro content. Future automation may; for now retros are for humans.
- `signal-retro` does not promote findings to recipes or rules. If a retro lesson is durable, the user creates a recipe via `signal-recipe` separately.
