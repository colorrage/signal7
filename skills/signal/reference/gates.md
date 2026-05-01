# Signal7 Gates and Verdicts

This file defines how `signal` and phase skills coordinate. It is the authority for phase transitions, approval gates, and verdict encoding.

## Ownership

- `signal` owns every mutation of `task.md` `phase:`, `awaiting:`, and `scope:`. After `signal-brief` returns `phase-complete`, `signal` reads the resolved `scope` from `brief.md` and mirrors it into `task.md`.
- Phase skills own their artifacts and return verdicts. They do not write any top-level `task.md` field.
- Worker skills own assigned asset files only. They never write `task.md`.
- `signal-task` may set `phase: cancelled` (and, in Phase 2, `phase: deferred`) for explicit user management operations and is the only skill besides `signal` that writes `task.md`.

## Verdict Encoding

Every phase skill response must end with exactly one fenced YAML block:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Brief written and ready to advance."
```

Allowed `verdict` values:

```text
awaiting-input
awaiting-approval
review-pass-approval-pending
phase-complete
redirect
```

`target` is required only for `redirect`, for example:

```yaml
signal_verdict:
  verdict: redirect
  target: create
  summary: "Review found issues; return to create."
```

`signal` parses only the final `signal_verdict` block. Human-readable prose above the block is for the user and must not be treated as machine state.

A redirect target must name a phase whose skill is implemented. Phase 2 implements `plan` and `plan-review`, so quick-overflow work may now emit `redirect target: plan`.

## Phase Tables

### quick — Phase 1 (implemented)

```text
brief -> create -> review -> publish -> done
```

### campaign — Phase 2 (implemented)

```text
brief -> plan -> plan-review -> create -> review -> publish -> done
```

Campaign scope enters planning after the brief approval gate.

### strategy

```text
brief -> create -> done
```

`signal-create` stamps `research` or `pricing` and dispatches `signal-research` or `signal-price` directly. The task ends at `done` once the artifact is written; review and publish are skipped unless the artifact is later promoted into public content.

## Transition Rules

| Current phase | Verdict | signal action |
|---|---|---|
| any | awaiting-input | set `awaiting: user-input`; stop |
| any | awaiting-approval | set `awaiting: user-approval`; stop |
| review | review-pass-approval-pending | set `awaiting: user-approval`; stop |
| any | phase-complete | clear `awaiting`; advance by scope table |
| any | redirect | clear `awaiting`; set `phase: target`; dispatch target when safe |

After `signal-brief` returns `phase-complete`, before advancing, `signal` reads `brief.md` `scope` and writes it into `task.md`.

Approval-gated phases advance after approval without an extra checkpoint. Agent-completion phases may stop for a user checkpoint when the phase skill contract says to do so.

## Review Approval Semantics

On first review dispatch, `signal-review` runs the AI rubric and writes `review.md`. If all AI checks pass, it returns `review-pass-approval-pending`.

On redispatch while `review.md` already records AI pass, `signal-review` skips the AI rubric and checks approver rows only.

When `signal-review` returns `redirect target: create` (AI rubric fail, or a human rejection captured in approver rows), it first writes `status: needs-revision` on every asset that failed the rubric or was rejected by name, and populates `rejected_reason` on the rejected approver rows. Without these writes, `signal-create` would skip the failed assets and the rework loop would spin without producing a new revision.

Timeout behavior:

- If `auto_approve_on_timeout: true`, mark that approver `approved` with a timeout note.
- If `auto_approve_on_timeout: false`, mark that approver `escalated`, name the delegate or owner in the summary, and keep the approval gate open.
- `rejected` redirects to `create`.

## Recording User Responses

Approval/rejection gates are settled by user replies: `approve`, `reject: <reason>`, or a delegate paste-in. The orchestrator parses the reply only well enough to clear `awaiting` and redispatch the phase skill. The phase skill is responsible for writing the user's response into the relevant artifact:

- `signal-review` redispatch records the reply against the matching approver row in `review.md` (`status`, `comment`, `rejected_reason`, `recorded_response`, `updated_at`).
- For brief approval, `signal-brief` redispatch sets `## Approval` `Status: approved` (or rejected with reason) in `brief.md` before returning `phase-complete`.

A user reply that is not parseable as approve/reject/scoped-change is treated as `awaiting-input` and surfaced verbatim.

## Plan Review Dispatch — Phase 2

`signal-plan-review` must be dispatched in a fresh sub-agent context. Inline invocation is not permitted for this phase because independence from the plan author is part of the review guarantee. The wider per-skill dispatch contract is in `portability.md` § Dispatch Contract.

Portable wording is "dispatch `signal-plan-review` in an isolated sub-agent context." Claude Code's Task tool with `subagent_type: general-purpose` is the documented non-portable implementation option.

## Redirects

Known redirects:

- `review -> create`: AI review failed or human rejected. `signal-review` flips affected assets to `status: needs-revision` before returning the verdict.
- `plan-review -> plan`: plan review found fixable flaws.
- `create -> plan`: quick scope expanded past the asset ceiling. `signal` sets `scope: campaign` before dispatching `signal-plan`.

Redirect targets must be valid phase enum values from `data-model.md` and must name a phase whose skill is implemented at the time the verdict is emitted.
