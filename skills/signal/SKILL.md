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
| plan | signal-plan | implemented |
| plan-review | signal-plan-review | implemented |
| create | signal-create | implemented |
| review | signal-review | implemented |
| publish | signal-publish | implemented |

Dispatch mode for every phase skill is in `portability.md` § Dispatch Contract. `signal-plan-review` must run in a fresh sub-agent context; everything else is inline today.

Phase 2 implements campaign planning and plan review. Phase 3 ships the remaining content workers (`signal-copy`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`), so a campaign can mix social, long-form, image, video, translation, research, and pricing assets in a single content plan. `signal-worker` only blocks for asset types not in the routing table.

Strategy scope (`brief -> create -> done`) dispatches `signal-research` and/or `signal-price` directly and skips review/publish. If the user later turns the strategy artifact into public content, that becomes a follow-up task that runs through `signal-copy` or `signal-social`.

## Verdict Handling

Parse only the final fenced YAML `signal_verdict` block from the phase skill.

Apply `gates.md` exactly:

- `awaiting-input` -> `awaiting: user-input`
- `awaiting-approval` -> `awaiting: user-approval`
- `review-pass-approval-pending` -> `awaiting: user-approval`
- `phase-complete` -> clear awaiting and advance by scope table
- `redirect` -> clear awaiting, set phase to target, dispatch when safe

Before advancing past `signal-brief`'s `phase-complete`, read `brief.md` `scope` and write it into `task.md`.

On `redirect target: plan` from `signal-create`, set task `scope: campaign` before dispatching `signal-plan`. This is the quick-overflow conversion path.

Reject any `redirect` whose target names an unimplemented phase. Treat it as `awaiting-input` with an explanation.

Regenerate `dashboard.md` after phase advances. The `dashboard.md` template has `<TODO>` sentinels for `Phase` and `Awaiting`; substitute them on every regeneration.

Archive on `phase: done`.

## Independence

Do not read Hyper7 internals or invoke Hyper7 skills. IT, website, code, and SEO dependencies are represented as Signal `external_gate` fields.

## Output

Return a concise status summary to the user, including current task id, phase, and any open gate.
