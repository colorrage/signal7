---
dispatch_skill: signal
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: campaign-blocked-at-intake

Legacy-named fixture for campaign intake. In Phase 2, a multi-channel goal should no longer be refused at intake; `signal` should advance the approved campaign brief into planning.

This was reviewer #1 critical #1 ("quick-scope redirect to plan is a dead-end") and reviewer #4 critical #2 ("orchestrator dispatches a phase skill that doesn't exist").

## Snapshot point

The fixture freezes the system **after `signal-brief` finished triage and the user approved the brief**:

- `task.md` `phase: brief`, `awaiting: null`, `scope: unknown`
- `brief.md` written with `scope: campaign` and the channel/language list
- `compliance.md` written at `status: clear`

## Trigger

`signal` resumes the task, observes the approved campaign brief, mirrors `scope: campaign`, advances to `plan`, and dispatches `signal-plan`.

## Expected verdict

```yaml
signal_verdict:
  verdict: awaiting-approval
  target: null
  summary: "Campaign plan and asset stubs are ready for approval."
```

## Expected mutations

- `task.md` `scope: campaign`.
- `task.md` `phase: plan`, `awaiting: user-approval` after `signal-plan` returns.
- `content-plan.md` is written with `status: draft`.
- Asset stubs are written at the task root for the planned channels.

## Reviewer-flagged regressions guarded

- The old campaign-redirect-into-nonexistent-skill regression is now inverted: `signal-plan` exists, so campaign work must not be refused only because it is campaign-shaped.
- `intake-triage.md`: campaign classification should route into `plan`.
