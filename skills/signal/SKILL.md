---
name: signal
description: >
  Starts or resumes Signal7 business-operations workflow tasks using disk
  state, gates, verdicts, and phase skills.
---

# signal

Signal7 orchestrates business operations workflows. It owns top-level task routing and every mutation of `task.md` `phase:` and `awaiting:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/bootstrap.md`
- `skills/signal/reference/dashboard.md`
- `skills/signal/reference/archive.md`
- `skills/signal/reference/cross-system-boundaries.md`

## Bootstrap

Before writing, ensure `.signal/` exists per `bootstrap.md`.

## Routing

If the user names an existing `S<N>`, resume it from `.signal/tasks/` first, then `.signal/archive/`.

If no task exists for a new goal:

1. Allocate the next `S<N>` by scanning tasks and archive.
2. Create `.signal/tasks/S<N>-<slug>/task.md` from the template.
3. Seed `dashboard.md`.
4. Set `phase: brief`.
5. Dispatch `signal-brief`.

If `awaiting` is set and the user has not replied to the open gate, surface the gate and stop.

If the user replies to an open gate, clear `awaiting`, dispatch the current phase skill, then apply the returned verdict.

## Phase Dispatch

| phase | Skill |
|---|---|
| brief | signal-brief |
| plan | signal-plan |
| plan-review | signal-plan-review |
| create | signal-create |
| review | signal-review |
| publish | signal-publish |

`plan-review` must run in a fresh sub-agent context.

Phase 1 implements `brief`, `create`, `review`, and `publish` for quick scope. Campaign plan skills arrive in Phase 2.

## Verdict Handling

Parse only the final fenced YAML `signal_verdict` block from the phase skill.

Apply `gates.md` exactly:

- `awaiting-input` -> `awaiting: user-input`
- `awaiting-approval` -> `awaiting: user-approval`
- `review-pass-approval-pending` -> `awaiting: user-approval`
- `phase-complete` -> clear awaiting and advance by scope table
- `redirect` -> clear awaiting, set phase to target, dispatch when safe

Regenerate `dashboard.md` after phase advances.

Archive on `phase: done`.

## Independence

Do not read Hyper7 internals or invoke Hyper7 skills. IT, website, code, and SEO dependencies are represented as Signal `external_gate` fields.

## Output

Return a concise status summary to the user, including current task id, phase, and any open gate.

