---
dispatch_skill: signal-recipe
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: recipe-create-run

Tests two `signal-recipe` operations: `create` writes a recipe file to `.signal/recipes/`, and `run` reads the recipe, stages the steps, and updates `last_run` / `run_count` in the recipe frontmatter.

## Snapshot point

The fixture freezes the system **after** both operations completed:

- `.signal/recipes/test-recipe.md` exists with valid frontmatter (`name`, `description`, `created`, `last_run` set, `run_count: 1`).
- The recipe body has a `## Steps` section with two `/signal` commands.

## Trigger

User ran:

1. `signal-recipe create "test-recipe" description: "Brief one LinkedIn post about product testing"`
2. `signal-recipe run "test-recipe"`

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "run completed."
```

## Expected mutations

- `run` updates frontmatter: `last_run: <ISO timestamp>`, `run_count` incremented from 0 to 1.
- Steps are surfaced to the user as candidate commands (no auto-invoke).
- Recipe file remains intact with original steps.
