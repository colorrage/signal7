---
name: signal
description: >
  Starts or resumes Signal7 business-operations workflow tasks using disk
  state, gates, verdicts, and phase skills.
---

# signal

Signal7 orchestrates business operations workflows. It owns top-level task routing and every mutation of `task.md` `phase:`, `awaiting:`, and `scope:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/state-graph.md`
- `skills/signal/reference/bootstrap.md`
- `skills/signal/reference/dashboard.md`
- `skills/signal/reference/archive.md`
- `skills/signal/reference/portability.md`
- `skills/signal/reference/cross-system-boundaries.md`

`README.md` § Status is the authoritative inventory of what is implemented today. Anything in the reference docs not listed there is forward-looking.

## Bootstrap

Before writing, ensure `.signal/` exists per `bootstrap.md`.

## Routing

If the user names an existing `S<N>`, resume it from `.signal/tasks/` first. If only `.signal/archive/` has it, treat the task as read-only per `archive.md` and report status without restarting it.

If no task exists for a new goal:

1. Allocate the next `S<N>` by scanning `.signal/tasks/` and `.signal/archive/`.
2. Create `.signal/tasks/S<N>-<slug>/task.md` from the template.
3. Seed `dashboard.md`.
4. Set `phase: brief`.
5. Dispatch `signal-brief` (inline; see `portability.md` § Dispatch Contract).

If `awaiting` is set and the user has not replied to the open gate, surface the gate and stop.

If the user replies to an open gate, clear `awaiting`, dispatch the current phase skill, then apply the returned verdict.

## Phase Dispatch

| phase | Skill | Status |
|---|---|---|
| brief | signal-brief | implemented |
| create | signal-create | implemented |
| review | signal-review | implemented |
| publish | signal-publish | implemented |
| plan | signal-plan | **Phase 2 — not yet implemented** |
| plan-review | signal-plan-review | **Phase 2 — not yet implemented** |

Dispatch mode for every phase skill is in `portability.md` § Dispatch Contract. `signal-plan-review` (Phase 2) must run in a fresh sub-agent context; everything else is inline today.

If `task.md` reaches `phase: plan` or `phase: plan-review` while their skills are unimplemented (e.g. via a stale state file), `signal` does not attempt to dispatch. It surfaces a clear blocked message — "Phase 2 is not yet implemented. Cancel this task with `/signal-task cancel S<N>` or wait for `signal-plan` to ship." — and stops.

Phase 1 implements `brief`, `create`, `review`, and `publish` for quick scope. Strategy `brief` runs but most strategy `asset_type` workers are Phase 3.

## Verdict Handling

Parse only the final fenced YAML `signal_verdict` block from the phase skill.

Apply `gates.md` exactly:

- `awaiting-input` -> `awaiting: user-input`
- `awaiting-approval` -> `awaiting: user-approval`
- `review-pass-approval-pending` -> `awaiting: user-approval`
- `phase-complete` -> clear awaiting and advance by scope table
- `redirect` -> clear awaiting, set phase to target, dispatch when safe

Before advancing past `signal-brief`'s `phase-complete`, read `brief.md` `scope` and write it into `task.md`. If `scope: campaign` and `signal-plan` is not implemented, do not advance — set `awaiting: user-input` and surface a "campaign scope is Phase 2; not yet implemented. Rescope to a single channel/language or cancel?" message.

Reject any `redirect` whose target names an unimplemented phase. Treat it as `awaiting-input` with an explanation.

Regenerate `dashboard.md` after phase advances. The `dashboard.md` template has `<TODO>` sentinels for `Phase` and `Awaiting`; substitute them on every regeneration.

Archive on `phase: done`.

## Independence

Do not read Hyper7 internals or invoke Hyper7 skills. IT, website, code, and SEO dependencies are represented as Signal `external_gate` fields.

## Output

Return a concise status summary to the user, including current task id, phase, and any open gate.
