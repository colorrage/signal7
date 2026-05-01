---
name: signal-recipe
description: >
  User-invocable Signal7 recipe manager. Lists, reads, creates, updates,
  deletes, and runs procedural playbooks under `.signal/recipes/`.
---

# signal-recipe

`signal-recipe` manages user-defined procedural playbooks. A recipe is a markdown file that documents a repeatable sequence of Signal7 operations — "weekly newsletter," "competitor refresh," "launch-week social cadence." Recipes are not tasks; running a recipe may create one or more tasks.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/bootstrap.md`

## Recipes Folder

`.signal/recipes/<slug>.md` is one recipe per file. Each file has YAML frontmatter and a markdown body:

```markdown
---
name: weekly-linkedin-update
description: Three LinkedIn posts every Monday — product highlight, customer win, market take.
created: 2026-05-01T11:00:00
last_run: null
run_count: 0
---

# Weekly LinkedIn Update

## Steps

1. /signal Write a LinkedIn post about <this week's product highlight>
2. /signal Write a LinkedIn post about <a recent customer win>
3. /signal Write a LinkedIn post about <our take on a current market story>

## Notes

- Tone: confident but conversational.
- Always wait for AI review before requesting human approval.
```

The body is freeform markdown. The `## Steps` section, when present, is what `run` reads.

`signal-recipe` creates `.signal/recipes/` lazily on first write — bootstrap does not pre-create it.

## Operations

### `list`

Read-only. Print one line per recipe: `<name>  [last_run: <date or "never">]  <description>`. Sort alphabetically by `name`. If `.signal/recipes/` is missing or empty, say "no recipes."

### `show <name>`

Print the full recipe file (frontmatter + body). Read-only.

### `create <name> [description: <text>]`

Create a new recipe.

1. Bootstrap `.signal/` and `.signal/recipes/` if missing.
2. Reject if `<name>` is not kebab-case ASCII or already exists.
3. Write `.signal/recipes/<name>.md` with the frontmatter shown in § Recipes Folder, an empty `## Steps` block (placeholder bullet "TODO: list steps"), and any description the user gave.
4. Confirm the file path. The user fills in steps directly with their editor or via `update`.

### `update <name>`

Open-ended edit. The user describes the change in conversation; `signal-recipe` applies it to the file. `signal-recipe` does not invent step content — it only reorganises, renames, or transcribes user-supplied text.

If the user did not supply concrete change instructions, return `awaiting-input` with "Describe the change to apply."

### `delete <name>`

Remove the recipe file.

1. Confirm the file exists.
2. Delete `.signal/recipes/<name>.md`. The deletion is hard — `signal-recipe` does not move the file to an archive.
3. Confirm to the user.

### `run <name>`

Execute the recipe.

1. Read `.signal/recipes/<name>.md`. If the file is missing, return "recipe `<name>` not found."
2. Find the `## Steps` block. If absent or empty, return `awaiting-input` with "recipe `<name>` has no steps."
3. For each step that begins with `/signal` or `/signal-*`, surface the step to the user as a candidate command. **`signal-recipe` does not auto-invoke** — the user runs each step explicitly. The reasons:
   - Signal7 has no scheduling primitive.
   - Recipe steps may include placeholders (`<this week's product highlight>`) that need user substitution.
   - Phase gates (brief approval, review approval) belong to the user, not to a script.
4. Update the recipe frontmatter: `last_run: <ISO timestamp>`, `run_count: <previous + 1>`.
5. Confirm the run is staged and list the steps the user should execute.

If the user wants automated multi-step execution later, that is the Phase 4+ "recurring/automation" path noted in `future-skill-registry.md`.

## Output Contract

Allowed verdicts when chained from `signal`: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "<operation> completed."
```

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "<reason>"
```

## Notes

- Recipes are not tasks. They have no `S<N>` id and no phase. Running a recipe creates zero or more tasks via the `/signal` steps the user invokes.
- `signal-recipe` does not parse step content beyond detecting `/signal` lines. Any other body is descriptive and for humans.
- Recipes are durable on disk; they survive across sessions and across agents.
