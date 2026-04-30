---
dispatch_skill: signal-plan-review
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: plan-review-reject-rework

Guards the `signal-plan-review` phase skill that catches coverage gaps before asset creation. The brief requests two channels (LinkedIn and Instagram), but the approved `content-plan.md` only covers LinkedIn. `signal-plan-review` must detect the missing channel, write `plan-review.md` with the finding, and return `redirect target: plan` so `signal-plan` can fix the gap.

## Snapshot point

The fixture freezes the system **before `signal-plan-review` first dispatch**:

- `task.md` `phase: plan-review`, `scope: campaign`, `awaiting: null`
- `brief.md` approved with `channels: [linkedin, instagram]`, `languages: [en]`
- `content-plan.md` approved with `status: approved` — deliberate flaw: only LinkedIn in the channel/language matrix and asset plan; Instagram is missing
- `A1-linkedin-en.md` stub at `status: todo`, `asset_type: social-copy`, `channel: linkedin`
- `compliance.md` `status: clear`
- No `A2-instagram-en.md` stub exists (the plan missed it)

## Trigger

`signal` dispatches `signal-plan-review` in a fresh sub-agent context after the user approved `content-plan.md`.

## Expected verdict

```yaml
signal_verdict:
  verdict: redirect
  target: plan
  summary: "Campaign plan review found fixable issues."
```

## Expected mutations

- `plan-review.md` created at task root with required sections (`## Verdict`, `## Findings`, `## Coverage`, `## Asset Routing`, `## Compliance`, `## Timeline`, `## Required Changes`).
- `## Coverage` section explicitly names the missing Instagram channel — brief documents 2 channels, plan only covers 1.
- `## Required Changes` lists: add Instagram channel to channel/language matrix, create A2-instagram-en.md stub, update asset plan table with Instagram row.
- `## Verdict` records the `redirect target: plan` recommendation.
- Existing `content-plan.md` is NOT modified by plan-review — re-planning is the responsibility of `signal-plan` on redirect.

## Full loop (described, not replayed)

This fixture verifies the first step of the rework loop. On subsequent dispatches:

1. `signal-plan` receives the redirect, reads `plan-review.md`, adds the Instagram channel and A2 stub, updates `content-plan.md`, records changes in `## Revision Notes`, and returns `awaiting-approval`.
2. If any stubs from the original plan become obsolete during re-plan, `signal-plan` marks them `status: cancelled` with `cancelled_reason` rather than deleting them.
3. The user approves the revised plan, and `signal-plan` returns `phase-complete`.
4. `signal-plan-review` re-runs on the revised plan, confirms coverage is complete, and returns `phase-complete`.
