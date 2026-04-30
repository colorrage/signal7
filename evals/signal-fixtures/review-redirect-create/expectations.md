---
dispatch_skill: signal
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: review-redirect-create

Guards orchestrator-level routing between phase skills. When a user rejects an asset at the review approval gate, `signal-review` must return `redirect target: create`; `signal` must apply that redirect, dispatch `signal-create`, and advance back to review after the revised asset is generated.

## Snapshot point

The fixture freezes the system **after the user replied `reject: tone too casual`** but **before** `signal-review` redispatch has run:

- `task.md` `phase: review`, `awaiting: user-approval`
- `A1-linkedin-en.md` `status: done`, `review_ai_pass: true`
- `review.md` exists with the approver row at `status: pending` and a recorded user rejection in the dashboard decisions log

## Trigger

`signal` redispatches `signal-review` because the user reply matched the open `awaiting: user-approval` gate.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Assets created."
```

## Expected mutations

- `review.md` approver row updated:
  ```yaml
  status: rejected
  rejected_reason: "tone too casual"
  comment: "tone too casual"
  recorded_response: "reject: tone too casual"
  updated_at: <now>
  ```
- `A1-linkedin-en.md` `revision: 1`.
- `A1-linkedin-en.md` `status: done` after the create re-dispatch completes.
- `prompts/A1-r1-signal-social.md` created.
- `task.md` `phase: review`, `awaiting: null` (set by `signal` after applying `signal-create`'s `phase-complete` verdict).

## Orchestrator routing

This fixture covers two skill dispatches with an orchestrator routing step between them:

1. `signal-review` returns `redirect target: create` (the dispatched skill's verdict).
2. `signal` applies the redirect: clears `awaiting`, sets `phase: create`, and dispatches `signal-create`.
3. `signal-create` regenerates the rejected asset, increments `revision`, writes a new prompt, and returns `phase-complete`.
4. `signal` applies `phase-complete` and advances the task to `phase: review`.

The routing step is the critical orchestrator responsibility — verifying that `signal` correctly translates a `redirect` verdict into a phase change and a follow-on dispatch, rather than breaking on an unrecognised or non-existent target.

## Reviewer-flagged regressions guarded

- The campaign-redirect-into-nonexistent-skill regression from T1: `signal` must validate that the redirect target names an implemented phase skill before dispatching. `create` is implemented in Phase 1; this fixture confirms the resolved path.
- Approver row rejection fields (`rejected_reason`, `recorded_response`) must be populated from the user's reply.
