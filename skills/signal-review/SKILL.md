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
- `skills/signal/reference/state-graph.md`
- `skills/signal/reference/compliance-and-audit.md`
- task `brief.md`
- task `compliance.md`
- `.signal/context/brand.md`
- `.signal/context/approved-claims.md` when present

## First Dispatch

1. Read all non-cancelled asset files.
2. Block if `compliance.md` is missing; `signal-brief` should have created it.
3. Block if `compliance.md` has `status: questions-open` or `status: blocked`. Return `awaiting-input` with a clear summary that compliance must be resolved first; do **not** redirect to `create` (there is no asset content to redirect).
4. If `.signal/context/approved-claims.md` is missing, treat it as `claims: []` and allow only claims explicitly cleared in `compliance.md`.
5. Run three passes on each asset (or only the source asset, in partial-review mode — see below):
   - brand compliance
   - claims/compliance against `approved-claims.md` and `compliance.md`
   - quality and channel fit
6. Set each reviewed asset `review_ai_pass: true` or `false`.
7. **For every asset whose AI rubric failed, set `status: needs-revision`** so `signal-create` picks it up on redirect. Without this flip the rework loop spins because `signal-create` only dispatches `todo` and `needs-revision` assets.
8. Write `review.md` with findings and approver rows (per-approver schema in `data-model.md`).
9. If any AI pass failed, return `redirect target: create`.
10. If AI passes for all reviewed assets, return `review-pass-approval-pending`.

### Partial review (translation flow)

When `signal-create` redirects with `target: review` and the eligible set contained translation assets whose source had `review_ai_pass: null`, run the rubric on the source asset(s) only. Set `review_ai_pass` on the source(s) and return `redirect target: create` so the translations become eligible. On the next review dispatch (after translations are generated), reuse the source's prior pass without rerunning the rubric on it; review the translations only.

## Redispatch (after the user replies to the approval gate)

If `review.md` already records AI pass for every reviewed asset, skip the AI rubric and inspect approver rows. Record the user's reply against the matching approver row in `review.md` before evaluating:

- `approve` from the named approver: set `status: approved`, `comment: "<reply text>"`, `recorded_response: "<reply text>"`, `updated_at: <now>`.
- `reject: <reason>`: set `status: rejected`, `rejected_reason: "<reason>"`, `recorded_response: "<reply text>"`, `updated_at: <now>`. Then for every asset named in the rejection (or every asset, when no scoping is given), set `status: needs-revision` and return `redirect target: create`.
- A delegate paste-in: capture the delegate's decision in the same fields and treat it as the named approver's response.

If the reply is not parseable, leave the row unchanged and return `awaiting-input` with the original prompt.

Status handling after recording the reply:

- `pending`: keep waiting unless timed out.
- `approved`: resolved.
- `rejected`: redirect to `create` (after the asset-status flip).
- `escalated`: keep approval gate open and name the owner/delegate.

On timeout:

- if `auto_approve_on_timeout: true`, mark `approved` with a timeout comment.
- otherwise mark `escalated`.

## Output Contract

Allowed verdicts: `awaiting-input`, `review-pass-approval-pending`, `redirect`, `phase-complete`.

Compliance blocked:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "compliance.md status is questions-open; resolve before review."
```

AI pass, human approval pending:

```yaml
signal_verdict:
  verdict: review-pass-approval-pending
  target: null
  summary: "AI review passed; human approval is pending."
```

Source asset(s) ready in partial-review mode (translation flow, Phase 3):

```yaml
signal_verdict:
  verdict: redirect
  target: create
  summary: "Source assets passed review; dispatching translations."
```

AI failed or human rejected (assets flipped to needs-revision):

```yaml
signal_verdict:
  verdict: redirect
  target: create
  summary: "Review found issues; affected assets returned for revision."
```

Approved:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Review complete."
```
