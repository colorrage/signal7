# Signal7 — Agent Instructions

Signal7 is a skill-based AI agent workflow system for business operations. All skills live under `signal7/skills/`. Each skill is a folder with a `SKILL.md` file defining its behavior.

## Skill naming

- Orchestrator: `signal/SKILL.md`
- Phase skills: `signal-brief/`, `signal-plan/`, `signal-create/`, `signal-review/`, `signal-publish/`, `signal-worker/`
- Management skills: `signal-task/`, `signal-backlog/`, `signal-recipe/`, `signal-handoff/`, `signal-retro/`, `signal-team/`
- Content workers: `signal-copy/`, `signal-social/`, `signal-image/`, `signal-video/`, `signal-translate/`, `signal-research/`, `signal-price/`

## State

All task state lives under `.signal/` in the project root (not inside this repo). Skill files are read-only definitions; they never write into their own skill folder.

## Relationship to Hyper7

Signal7 is a parallel system to Hyper7. They share the same architectural patterns (disk state, gate/verdict system, worker dispatch) but operate independently. Cross-system references use task IDs (e.g., "Hyper T12 produced the product page; Signal T3 is the launch campaign").
