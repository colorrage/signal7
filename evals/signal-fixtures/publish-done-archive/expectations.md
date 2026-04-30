---
dispatch_skill: signal
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: publish-done-archive

Guards the full terminal transition from publish to done to archive. When all assets are already published, `signal-publish` returns `phase-complete`, `signal` advances to `phase: done`, regenerates the dashboard, and moves the task folder to `.signal/archive/`.

## Snapshot point

The fixture freezes the system **after publish completed but before the terminal transition**:

- `task.md` `phase: publish`, `awaiting: null`, `scope: quick`
- `A1-linkedin-en.md` `status: done`, `review_ai_pass: true`
- `review.md` exists with all approver rows `status: approved`
- `publish-log.md` has one `status: published` entry for `S1.A1`

## Trigger

`signal` dispatches `signal-publish`. `signal-publish` detects all assets already published in the ledger, records `skipped-duplicate` for each, and returns `phase-complete`. `signal` then applies the terminal transition.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Publish ledger updated."
```

## Expected mutations

- `task.md` `phase: done`, `awaiting: null` (set by `signal` after `phase-complete`).
- Dashboard regenerated with `## Status` reading `Phase: done`, `Awaiting: none`.
- The task folder moves to `.signal/archive/S1-publish-complete/`.

## Orchestrator routing

This fixture covers the full terminal transition across two steps:

1. `signal-publish` returns `phase-complete` (the dispatched skill's verdict).
2. `signal` applies `phase-complete`: advances `phase` to `done`, regenerates `dashboard.md` with the new phase, and moves the folder to `.signal/archive/`.

The archive step is the critical orchestrator responsibility — `signal` must recognise `phase: done` as terminal, perform the folder move, and ensure the dashboard reflects the final state. The existing `publish-duplicate-skipped` fixture only tests the publish idempotency contract; this fixture tests the full terminal transition that `signal` must complete after publish finishes.

## Reviewer-flagged regressions guarded

- The campaign-redirect-into-nonexistent-skill regression from T1: `signal` must validate every phase advance target. `done` must be recognised as terminal and result in archiving, not a dispatch attempt.
- Dashboard regeneration on phase advance must reflect `Phase: done` and clear `Awaiting`.
- Folder move to archive must preserve all internal files without corruption.
