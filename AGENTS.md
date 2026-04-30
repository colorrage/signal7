# Signal7 — Agent Instructions

Signal7 is a skill-based AI agent workflow system for business operations. All skills live under `signal7/skills/`. Each skill is a folder with a `SKILL.md` file defining its behavior.

## Skill naming

- Orchestrator: `signal/SKILL.md`
- Phase skills: `signal-brief/`, `signal-create/`, `signal-review/`, `signal-publish/`, `signal-worker/` *(implemented)*; `signal-plan/`, `signal-plan-review/` *(planned, Phase 2)*
- Management skills: `signal-task/` *(implemented — cancel + status only)*; `signal-backlog/`, `signal-recipe/`, `signal-handoff/`, `signal-retro/`, `signal-team/` *(planned, Phase 2/3)*
- Content workers: `signal-social/` *(implemented)*; `signal-copy/`, `signal-image/`, `signal-video/`, `signal-translate/`, `signal-research/`, `signal-price/` *(planned, Phase 2/3)*

`README.md` § Status is the source of truth for what is shipped today. Reference docs (`data-model.md`, `gates.md`, etc.) describe the eventual contract — anything not listed in Status as "implemented" is forward-looking.

## State

All task state lives under `.signal/` in the project root (not inside this repo). Skill files are read-only definitions; they never write into their own skill folder.

## Relationship to Hyper7

Signal7 is a parallel system to Hyper7. They share the same architectural patterns (disk state, gate/verdict system, worker dispatch) but operate independently. Cross-system references use task IDs (e.g., "Hyper T12 produced the product page; Signal S3 is the launch campaign").
