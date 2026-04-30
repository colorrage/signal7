---
dispatch_skill: signal-review
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: compliance-default-clear

Guards the compliance template default flip. A non-regulated brief leaves `compliance.md` at the new default (`status: clear`); `signal-review` does **not** halt at the compliance check.

This was reviewer #4 critical #5 ("compliance.md template default is a silent halt trap").

## Snapshot point

The fixture freezes the system **just before `signal-review` first dispatch**:

- `task.md` `phase: review`, `awaiting: null`
- `A1-linkedin-en.md` `status: done`, `review_ai_pass: null`
- `compliance.md` at `status: clear` (the new default)
- No `review.md` yet

## Trigger

`signal` dispatches `signal-review` for the first time.

## Expected verdict

`signal-review` must NOT return `awaiting-input` for compliance — `compliance.md` is `clear`. It runs the AI rubric, writes `review.md`, and returns:

```yaml
signal_verdict:
  verdict: review-pass-approval-pending
  target: null
  summary: "AI review passed; human approval is pending."
```

## Expected mutations

- `review.md` written with `status: pending` on the approver row.
- `A1-linkedin-en.md` `review_ai_pass: true`.

## Reviewer-flagged regressions guarded

- Issue #4 critical #5: this fixture should fail if anyone reverts the compliance template to `status: questions-open`.
- Issue #1 minor #12: confirms the explanatory comment in `templates/compliance.md` documents the deliberate default.
- `signal-brief` no longer needs to flip `clear` → `questions-open` for non-regulated tasks; this fixture also confirms `signal-brief` did **not** raise the status.
