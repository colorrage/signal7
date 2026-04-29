---
name: signal-review
description: >
  Internal Signal7 phase skill that performs AI review, writes review.md,
  manages human approval rows, and returns review verdicts.
user-invocable: false
---

# signal-review

Review generated assets for brand, claims, compliance, and channel fit. Do not write `task.md` `phase:` or `awaiting:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/compliance-and-audit.md`
- task `brief.md`
- task `compliance.md`
- `.signal/context/brand.md`
- `.signal/context/approved-claims.md` when present

## First Dispatch

1. Read all non-cancelled asset files.
2. Block if `compliance.md` is missing; `signal-brief` should have created it.
3. Block if `compliance.md` has `status: questions-open` or `status: blocked`.
4. If `.signal/context/approved-claims.md` is missing, treat it as `claims: []` and allow only claims explicitly cleared in `compliance.md`.
5. Run three passes:
   - brand compliance
   - claims/compliance against `approved-claims.md` and `compliance.md`
   - quality and channel fit
6. Set each asset `review_ai_pass: true` or `false`.
7. Write `review.md` with findings and approver rows.
8. If any AI pass fails, return redirect to `create`.
9. If AI passes, return `review-pass-approval-pending`.

## Redispatch

If `review.md` already records AI pass, skip the AI rubric and inspect approver rows.

Status handling:

- `pending`: keep waiting unless timed out.
- `approved`: resolved.
- `rejected`: redirect to `create`.
- `escalated`: keep approval gate open and name the owner/delegate.

On timeout:

- if `auto_approve_on_timeout: true`, mark `approved` with a timeout comment.
- otherwise mark `escalated`.

## Output Contract

AI pass, human approval pending:

```yaml
signal_verdict:
  verdict: review-pass-approval-pending
  target: null
  summary: "AI review passed; human approval is pending."
```

Rejected or AI failed:

```yaml
signal_verdict:
  verdict: redirect
  target: create
  summary: "Review found issues; return to create."
```

Approved:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Review complete."
```
