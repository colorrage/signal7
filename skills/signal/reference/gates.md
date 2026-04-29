# Signal7 Gates and Verdicts

This file defines how `signal` and phase skills coordinate. It is the authority for phase transitions, approval gates, and verdict encoding.

## Ownership

- `signal` owns `task.md` `phase:` and `awaiting:`.
- Phase skills own their artifacts and return verdicts. They do not write top-level `phase:` or `awaiting:`.
- Worker skills own assigned asset files only. They never write `task.md`.
- `signal-task` may set `phase: deferred` or `phase: cancelled` for explicit user management operations.

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
  target: plan
  summary: "Quick brief expanded past the asset ceiling; campaign planning is required."
```

`signal` parses only the final `signal_verdict` block. Human-readable prose above the block is for the user and must not be treated as machine state.

## Phase Tables

### quick

```text
brief -> create -> review -> publish -> done
```

### campaign

```text
brief -> plan -> plan-review -> create -> review -> publish -> done
```

### strategy

```text
brief -> create -> done
```

Strategy `create` produces an internal research or pricing artifact and skips review/publish unless the user turns it into public content.

## Transition Rules

| Current phase | Verdict | signal action |
|---|---|---|
| any | awaiting-input | set `awaiting: user-input`; stop |
| any | awaiting-approval | set `awaiting: user-approval`; stop |
| review | review-pass-approval-pending | set `awaiting: user-approval`; stop |
| any | phase-complete | clear `awaiting`; advance by scope table |
| any | redirect | clear `awaiting`; set `phase: target`; dispatch target when safe |

Approval-gated phases advance after approval without an extra checkpoint. Agent-completion phases may stop for a user checkpoint when the phase skill contract says to do so.

## Review Approval Semantics

On first review dispatch, `signal-review` runs the AI rubric and writes `review.md`. If all AI checks pass, it returns `review-pass-approval-pending`.

On redispatch while `review.md` already records AI pass, `signal-review` skips the AI rubric and checks approver rows only.

Timeout behavior:

- If `auto_approve_on_timeout: true`, mark that approver `approved` with a timeout note.
- If `auto_approve_on_timeout: false`, mark that approver `escalated`, name the delegate or owner in the summary, and keep the approval gate open.
- `rejected` redirects to `create`.

## Plan Review Dispatch

`signal-plan-review` must be dispatched in a fresh sub-agent context. Inline invocation is not permitted for this phase because independence from the plan author is part of the review guarantee.

Portable wording is "dispatch `signal-plan-review` in an isolated sub-agent context." Claude Code's Task tool with `subagent_type: general-purpose` is the documented non-portable implementation option.

## Redirects

Known redirects:

- `create -> plan`: quick scope expanded past the asset ceiling.
- `review -> create`: AI review failed or human rejected.
- `plan-review -> plan`: plan review found fixable flaws.

Redirect targets must be valid phase enum values from `data-model.md`.
