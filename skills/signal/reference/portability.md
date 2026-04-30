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

User-facing skills omit `user-invocable: false`. Internal phase and worker skills set it. Workers additionally declare `worker_version: <int>`.

## Distribution

No plugin manifest is required. No Signal CLI is required. Install by copying or symlinking the whole `skills/` suite into agent skill directories such as:

- `~/.claude/skills/`
- `~/.codex/skills/`
- `~/.agents/skills/`
- `~/.pi/agent/skills/`

The repo-local `install-signal` helper exists only for local development and user convenience.

## Tool Neutrality

Skill bodies use host-neutral language such as "invoke the `signal-review` skill." The only documented exception is sub-agent dispatch, where hosts differ. Claude Code's Task tool with `subagent_type: general-purpose` is a known implementation option, not a general dependency.

## Dispatch Contract

`signal` dispatches phase skills, and `signal-create` dispatches workers. Each call site has a defined dispatch contract — *inline* (the orchestrator runs the skill in its own context) vs *sub-agent* (a fresh isolated context).

| Call site | Skill | Mode | Why |
|---|---|---|---|
| `signal` | `signal-brief` | inline | One clarification per turn; the orchestrator carries conversational context |
| `signal` | `signal-create` | inline | Coordinates per-asset worker dispatch; no independence requirement |
| `signal` | `signal-review` | inline | Reads/writes review.md and approver rows in the same conversational context |
| `signal` | `signal-publish` | inline | Append-only ledger writes; no independence requirement |
| `signal` | `signal-plan` *(Phase 2)* | inline | Plan author; the adversarial step is `signal-plan-review` |
| `signal` | `signal-plan-review` *(Phase 2)* | **sub-agent** | Independence from the plan author is the design guarantee. Inline dispatch is **not permitted**. |
| `signal-create` | `signal-worker` | inline or sub-agent | Single-asset dispatch. Inline is fine when assets run sequentially; sub-agent dispatch is required for the parallel batch path so disjoint-`writes[]` assets actually execute concurrently. |
| `signal-worker` | content workers (`signal-social`, …) | inline or sub-agent | Match the parent dispatch mode. |

"Sub-agent" means the host's isolated-context dispatch primitive. On Claude Code, that is the Task tool with `subagent_type: general-purpose`. On other hosts the equivalent primitive applies. Skill bodies must use the host-neutral phrase "dispatch in a sub-agent context"; the Claude Code mapping is documentation, not contract.

## No Hidden Runtime

Signal7 has no database, server, or background daemon. Durable state is markdown/YAML on disk under `.signal/`.
