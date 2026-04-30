# Fixture: review-rejection-rework

Guards the rework loop. When a human rejects an asset at the review approval gate, `signal-review` must:

1. Write the rejection into the matching approver row in `review.md` (`status: rejected`, `rejected_reason`, `recorded_response`).
2. Flip the affected asset's `status` from `done` to `needs-revision`.
3. Return `redirect target: create`.

Without step 2, `signal-create` skips the failed asset on the redirect and the loop spins without producing a new revision. This was reviewer #2's "high" finding ("Review failures cannot trigger rework").

## Snapshot point

The fixture freezes the system **after the user replied `reject: tone too casual`** but **before** `signal-review` redispatch has run:

- `task.md` `phase: review`, `awaiting: user-approval`
- `A1-linkedin-en.md` `status: done`, `review_ai_pass: true`
- `review.md` exists with the approver row at `status: pending` and a recorded user reply hint in the dashboard decisions log

## Trigger

`signal` redispatches `signal-review` because the user reply matched the open `awaiting: user-approval` gate.

## Expected verdict

```yaml
signal_verdict:
  verdict: redirect
  target: create
  summary: "Review found issues; affected assets returned for revision."
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
- `A1-linkedin-en.md` `status: needs-revision`.
- `task.md` `phase: create`, `awaiting: null` (set by `signal` after applying the redirect).

## Reviewer-flagged regressions guarded

- Issue #2 high: review failures must trigger rework, not loop without revision.
- Issue #4 warning: rejection/redirect path must be documented in approval gates.
- `data-model.md` `status: needs-revision` enum value must exist (added in this task).
