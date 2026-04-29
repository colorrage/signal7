# Signal7 Portability

Signal7 ships as Agent Skills: plain folders under `skills/`, each with a `SKILL.md` file and optional `reference/` or `templates/` files.

## Skill Frontmatter

Every `SKILL.md` must have parseable frontmatter:

```yaml
---
name: signal-example
description: >
  Short trigger description.
user-invocable: false
---
```

User-facing skills omit `user-invocable: false`. Internal phase and worker skills set it.

## Distribution

No plugin manifest is required. No Signal CLI is required. Install by copying or symlinking the whole `skills/` suite into agent skill directories such as:

- `~/.claude/skills/`
- `~/.codex/skills/`
- `~/.agents/skills/`
- `~/.pi/agent/skills/`

The repo-local `install-signal` helper exists only for local development and user convenience.

## Tool Neutrality

Skill bodies use host-neutral language such as "invoke the `signal-review` skill." The only documented exception is sub-agent dispatch, where hosts differ. Claude Code's Task tool with `subagent_type: general-purpose` is a known implementation option, not a general dependency.

## No Hidden Runtime

Signal7 has no database, server, or background daemon. Durable state is markdown/YAML on disk under `.signal/`.
